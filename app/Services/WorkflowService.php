<?php

namespace App\Services;

use App\Contracts\ConcernantStructureWorkflow;
use App\Contracts\PilotableParWorkflow;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use App\Services\Institution\WorkflowInstitutionResolver;
use App\Services\Workflow\SodPolicyService;
use App\Services\Workflow\VisaElectroniqueService;
use App\Services\Workflow\WorkflowAuditService;
use App\Services\Workflow\WorkflowNotificationService;
use App\Services\Workflow\WorkflowTransitionResolver;
use App\Services\Workflow\WorkflowVersionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moteur de workflow (D14) : démarre un circuit sur un objet, fait progresser
 * les étapes affectées à des rôles, et applique les transitions d'état.
 */
class WorkflowService
{
    public function __construct(
        private readonly WorkflowInstitutionResolver $institution,
        private readonly WorkflowAuditService $auditWorkflow,
        private readonly WorkflowNotificationService $notifications,
        private readonly VisaElectroniqueService $visas,
        private readonly WorkflowTransitionResolver $transitions,
        private readonly WorkflowVersionService $versions,
        private readonly SodPolicyService $sod,
    ) {}
    /** Démarre le circuit identifié par $code sur $objet. */
    public function demarrer(Model $objet, string $code, User $user): WorkflowInstance
    {
        $workflow = Workflow::with('etapes')->where('code', $code)->where('actif', true)->firstOrFail();

        if ($workflow->etapes->isEmpty()) {
            throw new RuntimeException("Le circuit « {$code} » ne comporte aucune étape.");
        }

        if ($objet->workflowInstances()->where('statut', 'en_cours')->exists()) {
            throw new RuntimeException('Un circuit est déjà en cours sur cet objet.');
        }

        return DB::transaction(function () use ($objet, $workflow, $user) {
            $version = $this->versions->versionCourante($workflow);

            $instance = $objet->workflowInstances()->create([
                'workflow_id' => $workflow->id,
                'workflow_version_id' => $version?->id,
                'objet_version' => $objet->getAttribute('version') ?? null,
                'statut' => 'en_cours',
                'etape_courante' => $workflow->etapes->min('ordre') ?? 1,
                'demarre_par' => $user->id,
            ]);

            foreach ($workflow->etapes as $etape) {
                $instance->etapes()->create([
                    'workflow_etape_id' => $etape->id,
                    'ordre' => $etape->ordre,
                    'role' => $etape->role,
                    'statut' => 'en_attente',
                ]);
            }

            if ($objet instanceof PilotableParWorkflow) {
                $objet->auWorkflowDemarre();
            }

            $instance = $instance->fresh(['workflow', 'etapes']);
            $this->appliquerEcheanceEtapeCourante($instance, $workflow);
            $this->auditWorkflow->enregistrerDemarrage($instance, $objet, $user);
            $this->notifications->notifierEtapeCourante($instance);

            return $instance->load('etapes');
        });
    }

    /** $user peut-il agir sur l'étape courante de $instance ? */
    public function peutAgir(WorkflowInstance $instance, User $user): bool
    {
        if ($instance->statut !== 'en_cours') {
            return false;
        }

        return $this->etapeTraitablePar($instance, $user) !== null;
    }

    /** Valide l'étape courante ; fait avancer ou clôt le circuit. */
    public function valider(WorkflowInstance $instance, User $user, ?string $observation = null): void
    {
        $this->agir($instance, $user, 'valide', $observation);
    }

    /** Rejette : termine le circuit en statut rejeté. */
    public function rejeter(WorkflowInstance $instance, User $user, string $observation, ?string $categorieRejet = null): void
    {
        if (trim($observation) === '') {
            throw new RuntimeException('Le motif de rejet est obligatoire.');
        }
        if (! $categorieRejet) {
            throw new RuntimeException('La catégorie de rejet est obligatoire.');
        }

        $this->agir($instance, $user, 'rejete', $observation, $categorieRejet);
    }

    /** Renvoie pour correction à l'étape précédente (ou à la première). */
    public function renvoyer(WorkflowInstance $instance, User $user, string $observation): void
    {
        if (trim($observation) === '') {
            throw new RuntimeException('Le motif de renvoi est obligatoire.');
        }

        $this->agir($instance, $user, 'renvoye', $observation);
    }

    /** @throws RuntimeException */
    public function traiterDecision(
        WorkflowInstance $instance,
        User $user,
        string $decision,
        ?string $observation = null,
        ?string $categorieRejet = null,
    ): void {
        match ($decision) {
            'valider' => $this->valider($instance, $user, $observation),
            'rejeter' => $this->rejeter($instance, $user, $observation ?? '', $categorieRejet),
            'renvoyer' => $this->renvoyer($instance, $user, $observation ?? ''),
            default => throw new RuntimeException("Décision workflow inconnue : {$decision}"),
        };
    }

    private function agir(WorkflowInstance $instance, User $user, string $decision, ?string $observation, ?string $categorieRejet = null): void
    {
        if (! $this->peutAgir($instance, $user)) {
            throw new RuntimeException("Vous n'êtes pas habilité à traiter l'étape courante.");
        }

        if ($decision === 'valide') {
            $instance = $instance->fresh(['workflow', 'etapes', 'objet']);
            $etape = $this->etapeTraitablePar($instance, $user);
            if ($etape === null) {
                throw new RuntimeException("Vous n'êtes pas habilité à traiter l'étape courante.");
            }
            $this->sod->assertValidationAllowed($instance, $user, $etape);
        }

        DB::transaction(function () use ($instance, $user, $decision, $observation, $categorieRejet) {
            $instance = $instance->fresh(['workflow', 'etapes', 'objet']);
            /** @var WorkflowInstanceEtape $etape */
            $etape = $decision === 'renvoye'
                ? ($this->etapeTraitablePar($instance, $user) ?? $instance->etapeCourante())
                : $this->etapeTraitablePar($instance, $user);

            if ($etape === null) {
                throw new RuntimeException("Aucune étape en attente pour votre rôle.");
            }

            $etape->update([
                'statut' => $decision,
                'acteur_id' => $user->id,
                'decision_le' => now(),
                'observation' => $observation,
                'categorie_rejet' => $decision === 'rejete' ? $categorieRejet : null,
            ]);

            if (in_array($decision, ['valide', 'rejete'], true)) {
                $this->visas->emettre(
                    $instance->fresh(['workflow']),
                    $etape->fresh(),
                    $user,
                    $decision === 'valide' ? 'validation' : 'rejet',
                    $observation,
                );
            }

            $objet = $instance->objet;

            match ($decision) {
                'valide' => $this->apresValidation($instance, $objet, $etape),
                'rejete' => $this->apresRejet($instance, $objet),
                'renvoye' => $this->apresRenvoi($instance, $etape),
            };

            $this->auditWorkflow->enregistrer(
                "workflow_{$decision}",
                $instance->fresh(['workflow']),
                $user,
                $etape->ordre,
                $observation,
                $categorieRejet,
            );
        });
    }

    private function etapeTraitablePar(WorkflowInstance $instance, User $user): ?WorkflowInstanceEtape
    {
        $instance = $instance->fresh(['workflow', 'etapes', 'objet']);
        $objet = $instance->relationLoaded('objet') ? $instance->objet : $instance->objet()->first();

        foreach ($instance->etapes()
            ->whereIn('ordre', $instance->ordresDuGroupeCourant())
            ->where('statut', 'en_attente')
            ->orderBy('ordre')
            ->get() as $etape) {
            if ($user->hasRole('administrateur') || $user->hasRole($etape->role)) {
                return $etape;
            }

            if ($objet instanceof ConcernantStructureWorkflow
                && $this->institution->peutAgirSurEtape($user, $objet->structureIdWorkflow(), $etape->role)) {
                return $etape;
            }
        }

        return null;
    }

    private function apresValidation(WorkflowInstance $instance, $objet, WorkflowInstanceEtape $etape): void
    {
        $instance = $instance->fresh(['workflow', 'etapes']);

        if (! $instance->ordreCourantTermine()) {
            return;
        }

        if ($objet instanceof PilotableParWorkflow) {
            $objet->auEtapeValidee($etape->ordre);
        }

        $prochainOrdre = $this->transitions->prochainOrdre(
            $instance,
            max($instance->ordresDuGroupeCourant()),
            $objet,
        );

        if ($prochainOrdre === null) {
            $instance->update(['statut' => 'valide']);
            if ($objet instanceof PilotableParWorkflow) {
                $objet->auWorkflowValide();
            }

            return;
        }

        $instance->update(['etape_courante' => $prochainOrdre]);
        $instance = $instance->fresh(['workflow', 'etapes']);
        $this->appliquerEcheanceEtapeCourante($instance);
        $this->notifications->notifierEtapeCourante($instance);
    }

    private function apresRejet(WorkflowInstance $instance, $objet): void
    {
        $instance->update(['statut' => 'rejete']);
        if ($objet instanceof PilotableParWorkflow) {
            $objet->auWorkflowRejete();
        }
    }

    private function apresRenvoi(WorkflowInstance $instance, WorkflowInstanceEtape $etape): void
    {
        $precedente = max(1, $etape->ordre - 1);

        // l'étape précédente redevient à traiter
        $instance->etapes()->where('ordre', $precedente)->update([
            'statut' => 'en_attente', 'acteur_id' => null, 'decision_le' => null,
            'echeance_le' => null, 'derniere_relance_le' => null,
        ]);
        $instance->update(['etape_courante' => $precedente]);
        $this->appliquerEcheanceEtapeCourante($instance->fresh(['workflow', 'etapes']));
    }

    private function appliquerEcheanceEtapeCourante(WorkflowInstance $instance, ?Workflow $workflow = null): void
    {
        $workflow ??= $instance->workflow;
        $template = $workflow?->etapes->firstWhere('ordre', $instance->etape_courante);
        $jours = $template?->delai_jours ?? config('workflow.relance.delai_defaut_jours', 5);
        $echeance = now()->addDays(max(1, (int) $jours));

        $instance->etapes()
            ->whereIn('ordre', $instance->ordresDuGroupeCourant())
            ->where('statut', 'en_attente')
            ->update([
                'echeance_le' => $echeance,
                'derniere_relance_le' => null,
            ]);
    }
}
