<?php

namespace App\Services\Workflow;

use App\Contracts\ConcernantStructureWorkflow;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use App\Models\WorkflowTache;
use App\Services\Institution\WorkflowInstitutionResolver;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationCriticite;
use App\Support\NotificationNature;
use App\Support\WorkflowObjetUrls;
use Illuminate\Support\Collection;

/** Notifications transverses workflow : corbeille et relances (P1 → dispatcher N-P0). */
class WorkflowNotificationService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly WorkflowInstitutionResolver $institution,
        private readonly SodPolicyService $sod,
    ) {}

    public function notifierEtapeCourante(WorkflowInstance $instance, string $type = 'etape_en_attente'): void
    {
        $instance = $instance->fresh(['workflow', 'objet']);
        $etape = $instance->etapeCourante();
        if (! $etape || $instance->statut !== 'en_cours') {
            return;
        }

        $destinataires = $this->destinataires($instance, $etape);
        if ($destinataires->isEmpty()) {
            return;
        }

        $libelle = WorkflowObjetUrls::libelle($instance->objet);
        $circuit = WorkflowObjetUrls::typeLibelle($instance);
        $typeCode = $type === 'relance_sla' ? 'workflow.relance_sla' : 'workflow.etape_en_attente';
        // La notification ouvre la tâche (fiche de traitement) ; à défaut, le dossier.
        $tache = WorkflowTache::query()->where('workflow_instance_etape_id', $etape->id)->ouvertes()->first();

        $this->dispatcher->envoyer(
            $destinataires,
            $typeCode,
            "{$circuit} — validation requise",
            "« {$libelle} » attend votre décision (étape {$etape->ordre} · {$etape->role})".($tache ? " — tâche {$tache->reference}." : '.'),
            $tache ? route('taches.show', $tache) : WorkflowObjetUrls::show($instance->objet),
            'workflow',
            NotificationCriticite::ATTENTION,
            meta: [
                'workflow_code' => $instance->workflow?->code,
                'instance_id' => $instance->id,
                'etape_ordre' => $etape->ordre,
                'tache_id' => $tache?->id,
            ],
            canaux: $this->canaux(),
            ressource: $instance->objet,
            actionRequise: $type !== 'relance_sla',
            cleDeduplication: "wf:{$instance->id}:{$etape->id}:".($tache?->id ?? 0).($type === 'relance_sla' ? ':relance' : ''),
        );
    }

    public function notifierRelance(WorkflowInstance $instance, int $joursRetard): void
    {
        $instance = $instance->fresh(['workflow', 'objet']);
        $etape = $instance->etapeCourante();
        if (! $etape) {
            return;
        }

        $destinataires = $this->destinataires($instance, $etape);
        $libelle = WorkflowObjetUrls::libelle($instance->objet);
        // Rattachée à l'étape et à sa tâche : la relance se clôt avec la décision et ouvre la fiche de traitement.
        $tache = WorkflowTache::query()->where('workflow_instance_etape_id', $etape->id)->ouvertes()->first();

        $this->dispatcher->envoyer(
            $destinataires,
            'workflow.relance_sla',
            'Alerte de retard — validation en attente',
            "« {$libelle} » dépasse l'échéance de {$joursRetard} jour(s) (étape {$etape->ordre}).",
            $tache ? route('taches.show', $tache) : WorkflowObjetUrls::show($instance->objet),
            'workflow',
            NotificationCriticite::AVERTISSEMENT,
            meta: [
                'workflow_code' => $instance->workflow?->code,
                'instance_id' => $instance->id,
                'etape_ordre' => $etape->ordre,
                'tache_id' => $tache?->id,
                'jours_retard' => $joursRetard,
            ],
            canaux: $this->canaux(),
            nature: NotificationNature::ALERTE,
            ressource: $instance->objet,
            actionRequise: true,
        );
    }

    /**
     * Rappel d'une action toujours en attente (§ 17) : niveau 1 puis 2 selon les délais paramétrés.
     * Destinataire : l'agent qui a pris la tâche en charge, sinon les acteurs attendus sur l'étape.
     */
    public function notifierRappel(WorkflowTache $tache, int $niveau): int
    {
        $instance = $tache->instance()->with(['workflow', 'objet'])->first();
        $etape = $tache->etape;
        if (! $instance || ! $etape) {
            return 0;
        }
        $destinataires = $tache->assigne_a
            ? User::query()->where('actif', true)->whereKey($tache->assigne_a)->get()
            : $this->destinataires($instance, $etape);

        return $this->dispatcher->envoyer(
            $destinataires,
            'workflow.relance_sla',
            ($niveau > 1 ? 'Deuxième rappel' : 'Rappel').' — '.WorkflowObjetUrls::typeLibelle($instance),
            '« '.WorkflowObjetUrls::libelle($instance->objet)."» attend toujours votre décision (tâche {$tache->reference}, étape {$tache->etape_ordre}).",
            route('taches.show', $tache),
            'workflow',
            $niveau > 1 ? NotificationCriticite::AVERTISSEMENT : NotificationCriticite::ATTENTION,
            meta: [
                'workflow_code' => $instance->workflow?->code,
                'instance_id' => $instance->id,
                'etape_ordre' => $tache->etape_ordre,
                'tache_id' => $tache->id,
                'rappel_niveau' => $niveau,
            ],
            canaux: $this->canaux(),
            nature: NotificationNature::RAPPEL,
            ressource: $instance->objet,
            actionRequise: true,
            cleDeduplication: "rappel:{$tache->id}:{$niveau}",
        );
    }

    /**
     * Escalade d'un retard important au responsable hiérarchique de l'unité validatrice (§ 17) :
     * information de supervision, sans droit d'agir à la place du titulaire (RN-04).
     *
     * @return Collection<int, User> destinataires effectifs
     */
    public function notifierEscalade(WorkflowTache $tache, int $joursRetard): Collection
    {
        $instance = $tache->instance()->with(['workflow', 'objet'])->first();
        if (! $instance) {
            return collect();
        }
        $objet = $instance->objet;
        $structureId = $objet instanceof ConcernantStructureWorkflow ? $objet->structureIdWorkflow() : $tache->structure_id;
        $unite = $this->institution->structureValidateur($structureId, $tache->role);
        $superieur = null;
        for ($s = $unite?->parent; $s && ! $superieur; $s = $s->parent) {
            $superieur = $s->responsable_user_id ? User::query()->where('actif', true)->find($s->responsable_user_id) : null;
        }
        $destinataires = collect([$superieur])->filter()
            ->reject(fn (User $u) => (int) $u->id === (int) $unite?->responsable_user_id)->values();
        if ($destinataires->isEmpty()) {
            return collect();
        }

        $this->dispatcher->envoyer(
            $destinataires,
            'workflow.escalade',
            'Escalade — dossier en retard',
            '« '.WorkflowObjetUrls::libelle($objet)."» est en attente depuis {$joursRetard} jour(s) au-delà de l'échéance (étape {$tache->etape_ordre} · {$tache->role}"
                .($unite ? ', '.$unite->code : '').').',
            WorkflowObjetUrls::show($objet),
            'workflow',
            NotificationCriticite::AVERTISSEMENT,
            meta: [
                'workflow_code' => $instance->workflow?->code,
                'instance_id' => $instance->id,
                'tache_id_suivie' => $tache->id,
                'jours_retard' => $joursRetard,
            ],
            canaux: $this->canaux(),
            nature: NotificationNature::ALERTE,
            ressource: $objet,
            cleDeduplication: "escalade:{$tache->id}",
        );

        return $destinataires;
    }

    /**
     * Décision portée sur un circuit (§ 6, NOT-002/003) : l'initiateur est informé du rejet, du retour pour
     * correction ou de la validation finale de son dossier.
     */
    public function notifierDecision(WorkflowInstance $instance, string $decision, ?User $acteur = null, ?string $motif = null): int
    {
        $instance = $instance->fresh(['workflow', 'objet']);
        $initiateurId = $this->sod->resolveInitiateur($instance) ?? $instance->demarre_par;
        $initiateur = $initiateurId ? User::query()->where('actif', true)->find($initiateurId) : null;
        if (! $initiateur || ($acteur && $acteur->id === $initiateur->id && $decision !== 'valide')) {
            return 0;
        }
        $objet = $instance->objet;
        $libelle = WorkflowObjetUrls::libelle($objet);
        [$titre, $texte, $criticite, $nature] = match ($decision) {
            'rejete' => ['Dossier rejeté', "« {$libelle} » a été rejeté", NotificationCriticite::AVERTISSEMENT, NotificationNature::INFORMATION],
            'renvoye' => ['Dossier retourné pour correction', "« {$libelle} » vous est retourné pour correction", NotificationCriticite::ATTENTION, NotificationNature::ACTION],
            default => ['Dossier approuvé', "« {$libelle} » a reçu sa validation finale", NotificationCriticite::INFORMATION, NotificationNature::INFORMATION],
        };

        return $this->dispatcher->envoyer(
            $initiateur,
            'workflow.decision',
            WorkflowObjetUrls::typeLibelle($instance).' — '.$titre,
            $texte.($acteur ? " par {$acteur->name}" : '').($motif ? " : {$motif}" : '.'),
            WorkflowObjetUrls::show($objet),
            'workflow',
            $criticite,
            meta: ['workflow_code' => $instance->workflow?->code, 'instance_id' => $instance->id, 'decision' => $decision],
            canaux: $this->canaux(),
            nature: $nature,
            ressource: $objet,
            cleDeduplication: "decision:{$instance->id}:{$decision}:".now()->format('YmdHi'),
        );
    }

    /** Réaffectation d'une tâche (§ 11) : le nouveau responsable est informé ; l'ancien ne peut plus agir. */
    public function notifierReaffectation(WorkflowTache $tache, User $cible, ?User $ancien, User $par, string $motif): void
    {
        $instance = $tache->instance()->with(['workflow', 'objet'])->first();
        $libelle = WorkflowObjetUrls::libelle($instance?->objet);
        $meta = ['workflow_code' => $instance?->workflow?->code, 'instance_id' => $tache->workflow_instance_id, 'etape_ordre' => $tache->etape_ordre, 'tache_id' => $tache->id];

        $this->dispatcher->envoyer(
            $cible,
            'workflow.reaffectation',
            'Tâche qui vous est réaffectée',
            "« {$libelle} » (tâche {$tache->reference}) vous a été réaffectée par {$par->name} : {$motif}",
            route('taches.show', $tache),
            'workflow',
            NotificationCriticite::ATTENTION,
            meta: $meta,
            canaux: $this->canaux(),
            ressource: $instance?->objet,
            actionRequise: true,
            cleDeduplication: "reaffectation:{$tache->id}:{$cible->id}",
        );
        if ($ancien && $ancien->id !== $cible->id) {
            $this->dispatcher->envoyer(
                $ancien,
                'workflow.decision',
                'Tâche réaffectée',
                "La tâche {$tache->reference} (« {$libelle} ») a été réaffectée à {$cible->name} : elle n'est plus à votre charge.",
                route('taches.show', $tache),
                'workflow',
                NotificationCriticite::INFORMATION,
                meta: ['instance_id' => $tache->workflow_instance_id, 'tache_id_suivie' => $tache->id],
                canaux: ['database'],
                nature: NotificationNature::INFORMATION,
                ressource: $instance?->objet,
            );
        }
    }

    /** E-mail en plus de l'in-app, sous réserve des canaux du type et des préférences du destinataire. */
    private function canaux(): array
    {
        return config('notifications.email.enabled', true) ? ['database', 'email'] : ['database'];
    }

    /**
     * Destinataires de l'action d'étape : la règle d'affectation partagée avec « Mes Tâches » et le moteur
     * (AffectationTacheService), filtrée par la séparation des fonctions (initiateur, étape précédente,
     * visa Direction du Budget) — la personne notifiée est exactement celle qui voit la tâche.
     *
     * @return Collection<int, User>
     */
    public function destinataires(WorkflowInstance $instance, WorkflowInstanceEtape $etape): Collection
    {
        $objet = $instance->objet;
        $structureId = $objet instanceof ConcernantStructureWorkflow ? $objet->structureIdWorkflow() : null;

        return app(AffectationTacheService::class)->titulaires($etape->role, $structureId)['users']
            ->filter(fn (User $u) => $this->sod->motifRefus($instance, $u, $etape) === null)
            ->values();
    }
}
