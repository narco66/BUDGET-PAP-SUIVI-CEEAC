<?php

namespace App\Services;

use App\Contracts\ConcernantStructureWorkflow;
use App\Contracts\PilotableParWorkflow;
use App\Contracts\RenvoiAuDemandeur;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use App\Services\Institution\WorkflowInstitutionResolver;
use App\Services\Workflow\SodPolicyService;
use App\Services\Workflow\TacheWorkflowService;
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
        private readonly TacheWorkflowService $taches,
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
            // Verrou du dossier puis nouveau contrôle : deux soumissions simultanées ne créent qu'une instance
            // (l'index unique « une instance en cours par dossier » en est la garantie ultime).
            $objet->newQuery()->whereKey($objet->getKey())->lockForUpdate()->first();
            if ($objet->workflowInstances()->where('statut', 'en_cours')->exists()) {
                throw new RuntimeException('Un circuit est déjà en cours sur cet objet.');
            }
            // Instance épinglée à la version publiée du circuit (P06) ; version initiale publiée si aucune ne l'est.
            $version = $this->versions->assurerVersion($workflow, $user);

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
                    'libelle' => $etape->libelle,   // conservé : l'historique ne dépend plus de la définition vivante
                    'statut' => 'en_attente',
                ]);
            }

            if ($objet instanceof PilotableParWorkflow) {
                $objet->auWorkflowDemarre();
            }

            $instance = $instance->fresh(['workflow', 'etapes']);
            $this->appliquerEcheanceEtapeCourante($instance, $workflow);
            $this->auditWorkflow->enregistrerDemarrage($instance, $objet, $user);
            $this->taches->synchroniser($instance);
            $this->notifications->notifierEtapeCourante($instance);
            // Les actions « à préparer » adressées hors circuit sur ce dossier sont désormais traitées.
            \App\Services\Notifications\NotificationActionResolver::cloreActionsHorsCircuit($objet);

            return $instance->load('etapes');
        });
    }

    /**
     * Le demandeur resoumet un dossier retourné pour correction : le circuit en cours reprend à l'étape
     * qui l'avait renvoyé (ses titulaires retrouvent la tâche et la notification).
     */
    public function reprendreApresCorrection(Model $objet, User $user): WorkflowInstance
    {
        if (! $objet instanceof RenvoiAuDemandeur || ! $objet->enCorrection()) {
            throw new RuntimeException('Ce dossier n\'est pas en attente de correction.');
        }

        return DB::transaction(function () use ($objet, $user) {
            $instance = $objet->workflowInstances()->where('statut', 'en_cours')->lockForUpdate()->first();
            if (! $instance) {
                throw new RuntimeException('Aucun circuit en cours à reprendre sur ce dossier.');
            }
            $objet->auCircuitRepris();

            $instance = $instance->fresh(['workflow', 'etapes', 'objet']);
            $this->appliquerEcheanceEtapeCourante($instance);
            $this->auditWorkflow->enregistrer('workflow_repris', $instance, $user, $instance->etape_courante, 'Dossier corrigé et resoumis.');
            $this->taches->synchroniser($instance);
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
    public function valider(WorkflowInstance $instance, User $user, ?string $observation = null, ?int $etapeAttendueId = null): void
    {
        $this->agir($instance, $user, 'valide', $observation, null, $etapeAttendueId);
    }

    /** Rejette : termine le circuit en statut rejeté. */
    public function rejeter(WorkflowInstance $instance, User $user, string $observation, ?string $categorieRejet = null, ?int $etapeAttendueId = null): void
    {
        if (trim($observation) === '') {
            throw new RuntimeException('Le motif de rejet est obligatoire.');
        }
        if (! $categorieRejet) {
            throw new RuntimeException('La catégorie de rejet est obligatoire.');
        }

        $this->agir($instance, $user, 'rejete', $observation, $categorieRejet, $etapeAttendueId);
    }

    /** Renvoie pour correction : au demandeur (RenvoiAuDemandeur), sinon à l'étape précédente (ou à la première). */
    public function renvoyer(WorkflowInstance $instance, User $user, string $observation, ?int $etapeAttendueId = null): void
    {
        if (trim($observation) === '') {
            throw new RuntimeException('Le motif de renvoi est obligatoire.');
        }

        $this->agir($instance, $user, 'renvoye', $observation, null, $etapeAttendueId);
    }

    /** @throws RuntimeException */
    public function traiterDecision(
        WorkflowInstance $instance,
        User $user,
        string $decision,
        ?string $observation = null,
        ?string $categorieRejet = null,
        ?int $etapeAttendueId = null,
    ): void {
        match ($decision) {
            'valider' => $this->valider($instance, $user, $observation, $etapeAttendueId),
            'rejeter' => $this->rejeter($instance, $user, $observation ?? '', $categorieRejet, $etapeAttendueId),
            'renvoyer' => $this->renvoyer($instance, $user, $observation ?? '', $etapeAttendueId),
            default => throw new RuntimeException("Décision workflow inconnue : {$decision}"),
        };
    }

    private function agir(WorkflowInstance $instance, User $user, string $decision, ?string $observation, ?string $categorieRejet = null, ?int $etapeAttendueId = null): void
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

        DB::transaction(function () use ($instance, $user, $decision, $observation, $categorieRejet, $etapeAttendueId) {
            // Verrou de l'instance : deux décisions concurrentes sont sérialisées ; la seconde constate
            // que l'étape n'est plus en attente (RG-07, RG-14).
            WorkflowInstance::query()->whereKey($instance->id)->lockForUpdate()->first();
            $instance = $instance->fresh(['workflow', 'etapes', 'objet']);
            /** @var WorkflowInstanceEtape $etape */
            $etape = $decision === 'renvoye'
                ? ($this->etapeTraitablePar($instance, $user) ?? $instance->etapeCourante())
                : $this->etapeTraitablePar($instance, $user);

            if ($etape === null) {
                throw new RuntimeException("Aucune étape en attente pour votre rôle.");
            }
            if ($etapeAttendueId !== null && $etape->id !== $etapeAttendueId) {
                throw new RuntimeException('Cette tâche a déjà été traitée ou a changé d\'étape. Rechargez avant d\'agir.');
            }

            $objetPrealable = $instance->objet;
            if ($decision === 'valide'
                && $objetPrealable instanceof \App\Models\Liquidation
                && $etape->role === 'controleur_financier') {
                app(\App\Services\Depense\ServiceFaitService::class)->assertCertifie($objetPrealable);
            }

            $etape->update([
                'statut' => $decision,
                'acteur_id' => $user->id,
                'decision_le' => now(),
                'observation' => $observation,
                'categorie_rejet' => $decision === 'rejete' ? $categorieRejet : null,
            ]);
            $tache = $this->taches->cloturerEtape($etape, $decision, $user, $observation);

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
            $this->notifierDecisionInitiateur($instance, $decision, $user, $observation);

            $this->auditWorkflow->enregistrer(
                "workflow_{$decision}",
                $instance->fresh(['workflow']),
                $user,
                $etape->ordre,
                $observation,
                $categorieRejet,
                array_filter([
                    'role' => $etape->role,
                    'etape' => $tache?->libelle_etape,
                    'tache_id' => $tache?->id,
                    'pour_compte_de' => $tache ? $this->taches->titulaireRepresente($tache, $user) : null,
                ], fn ($v) => $v !== null),
            );

            $this->taches->synchroniser($instance);
        });
    }

    private function etapeTraitablePar(WorkflowInstance $instance, User $user): ?WorkflowInstanceEtape
    {
        $instance = $instance->fresh(['workflow', 'etapes', 'objet']);
        $objet = $instance->relationLoaded('objet') ? $instance->objet : $instance->objet()->first();
        $structureId = $objet instanceof ConcernantStructureWorkflow ? $objet->structureIdWorkflow() : null;
        $affectations = app(\App\Services\Workflow\AffectationTacheService::class);

        foreach ($instance->etapes()
            ->whereIn('ordre', $instance->ordresDuGroupeCourant())
            ->where('statut', 'en_attente')
            ->orderBy('ordre')
            ->get() as $etape) {
            // Même règle d'affectation que les notifications et « Mes Tâches » : titulaire organique de
            // l'étape (ou son suppléant) ; à défaut de titulaire paramétré, tout porteur du rôle.
            if ($user->hasRole('administrateur') || $affectations->estTitulaire($user, $etape->role, $structureId)) {
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
        $this->taches->synchroniser($instance);
        $this->notifications->notifierEtapeCourante($instance);
    }

    /**
     * L'initiateur est informé du rejet, du retour pour correction et de la validation finale (référentiel § 6).
     * Émis dans la transaction (annulé avec elle) ; une panne de notification n'annule pas la décision (RN-12).
     */
    private function notifierDecisionInitiateur(WorkflowInstance $instance, string $decision, User $acteur, ?string $observation): void
    {
        $statut = $instance->fresh()?->statut;
        if ($decision === 'valide' && $statut !== 'valide') {
            return;   // étape intermédiaire : l'acteur suivant est notifié par notifierEtapeCourante
        }
        try {
            // Point de sauvegarde : sous PostgreSQL, une erreur SQL non isolée invaliderait toute la transaction.
            DB::transaction(fn () => $this->notifications->notifierDecision($instance, $decision, $acteur, $observation));
        } catch (\Throwable $e) {
            report($e);
        }
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
        $objet = $instance->objet;
        if ($objet instanceof RenvoiAuDemandeur) {
            // Retour au demandeur : le circuit reste sur l'étape qui renvoie, sans tâche de validation tant
            // que le dossier est en correction (la tâche de correction naît de la projection de préparation).
            $etape->update([
                'statut' => 'en_attente', 'acteur_id' => null, 'decision_le' => null,
                'echeance_le' => null, 'derniere_relance_le' => null,
            ]);
            $instance->update(['etape_courante' => $etape->ordre]);
            $objet->auWorkflowRenvoye();
            $this->taches->synchroniser($instance);

            return;
        }

        $precedente = max(1, $etape->ordre - 1);

        // l'étape précédente redevient à traiter
        $instance->etapes()->where('ordre', $precedente)->update([
            'statut' => 'en_attente', 'acteur_id' => null, 'decision_le' => null,
            'echeance_le' => null, 'derniere_relance_le' => null,
        ]);
        // L'étape qui renvoie redevient « à traiter » : restée « renvoye », plus personne ne pouvait la
        // traiter quand le circuit revenait sur elle (blocage dès l'étape 2). Sa décision reste tracée
        // par la tâche clôturée, l'audit workflow et le visa.
        if ($etape->ordre !== $precedente) {
            $etape->update([
                'statut' => 'en_attente', 'acteur_id' => null, 'decision_le' => null,
                'echeance_le' => null, 'derniere_relance_le' => null,
            ]);
        }
        $instance->update(['etape_courante' => $precedente]);
        $this->appliquerEcheanceEtapeCourante($instance->fresh(['workflow', 'etapes']));

        $objet = $instance->objet;
        if ($objet && method_exists($objet, 'auWorkflowRenvoye')) {
            $objet->auWorkflowRenvoye();
        }

        $this->taches->synchroniser($instance);
        $this->notifications->notifierEtapeCourante($instance->fresh());
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
