<?php

namespace App\Services\Alertes;

use App\Models\RelanceLog;
use App\Models\RelanceRule;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Pap\PapPilotageService;
use App\Support\NotificationCriticite;
use Illuminate\Support\Collection;

/** Moteur de relances métier paramétrables (N-P1). */
class RelanceMetierService
{
    public function __construct(
        private readonly NotificationDispatcher $notifications,
        private readonly PapPilotageService $papPilotage,
    ) {}

    public function executer(?int $exerciceId = null): int
    {
        $count = 0;

        foreach (RelanceRule::where('actif', true)->orderBy('module')->get() as $rule) {
            $count += match ($rule->code) {
                'pap.activite_retard' => $this->relancerPapActivites($rule, $exerciceId),
                'budget.validation_attente' => $this->relancerWorkflowCircuit($rule, 'BUDGET'),
                'depense.engagement_attente' => $this->relancerWorkflowCircuit($rule, 'ENGAGEMENT'),
                'depense.liquidation_attente' => $this->relancerWorkflowCircuit($rule, 'LIQUIDATION'),
                'recettes.encaissement_attente' => $this->relancerWorkflowCircuit($rule, 'RECETTES'),
                default => 0,
            };
        }

        return $count;
    }

    private function relancerPapActivites(RelanceRule $rule, ?int $exerciceId): int
    {
        if (! $exerciceId) {
            return 0;
        }

        $count = 0;
        $alertes = $this->papPilotage->alertes($exerciceId);

        foreach ($alertes['en_retard'] as $activite) {
            $code = $activite['code'] ?? 'activite';
            if ($this->relanceRecente($rule, null, $code)) {
                continue;
            }

            $titre = 'Relance — activité PAP en retard';
            $message = "L'activité {$code} présente un taux physique insuffisant ({$activite['taux_physique']} %).";

            $count += $this->emettreRelance($rule, $titre, $message, metadata: ['activite_code' => $code]);
        }

        return $count;
    }

    private function relancerWorkflowCircuit(RelanceRule $rule, string $circuitCode): int
    {
        $count = 0;

        WorkflowInstance::query()
            ->with(['workflow', 'objet'])
            ->where('statut', 'en_cours')
            ->whereHas('workflow', fn ($q) => $q->where('code', $circuitCode))
            ->whereHas('etapes', fn ($q) => $q->where('statut', 'en_attente')->where('echeance_le', '<', now()))
            ->each(function (WorkflowInstance $instance) use ($rule, &$count) {
                if ($this->relanceRecente($rule, $instance)) {
                    return;
                }

                $etape = $instance->etapeCourante();
                $libelle = $instance->objet?->reference ?? $instance->objet?->numero ?? "#{$instance->id}";
                $titre = "Relance — {$rule->libelle}";
                $message = "« {$libelle} » ({$instance->workflow?->code}) dépasse l'échéance à l'étape {$etape?->ordre}.";

                $count += $this->emettreRelance($rule, $titre, $message, $instance);
            });

        return $count;
    }

    private function emettreRelance(RelanceRule $rule, string $titre, string $message, ?WorkflowInstance $objet = null, array $metadata = []): int
    {
        $destinataires = $this->destinataires($rule, $objet);
        if ($destinataires->isEmpty()) {
            return 0;
        }

        $typeCode = $rule->notification_type_code ?? 'systeme.information';

        $this->notifications->envoyer(
            $destinataires,
            $typeCode,
            $titre,
            $message,
            route('workflow.validations'),
            $rule->module,
            NotificationCriticite::AVERTISSEMENT,
            meta: array_merge($metadata, ['relance_rule' => $rule->code]),
        );

        foreach ($destinataires as $user) {
            RelanceLog::create([
                'relance_rule_id' => $rule->id,
                'module' => $rule->module,
                'objet_type' => $objet?->getMorphClass(),
                'objet_id' => $objet?->getKey(),
                'destinataire_id' => $user->id,
                'canal' => 'database',
                'statut' => 'envoye',
                'titre' => $titre,
                'message' => $message,
                'metadata' => $metadata ?: null,
                'emis_le' => now(),
            ]);
        }

        return $destinataires->count();
    }

    /** @return Collection<int, User> */
    private function destinataires(RelanceRule $rule, ?WorkflowInstance $instance): Collection
    {
        $roles = $rule->roles_cibles ?? [];
        if ($instance?->etapeCourante()?->role) {
            $roles = array_unique(array_merge($roles, [$instance->etapeCourante()->role]));
        }

        if (empty($roles)) {
            $roles = ['commissaire'];
        }

        return User::query()->where('actif', true)->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))->get();
    }

    private function relanceRecente(RelanceRule $rule, ?WorkflowInstance $objet, ?string $cleMeta = null): bool
    {
        $query = RelanceLog::where('relance_rule_id', $rule->id)
            ->where('emis_le', '>=', now()->subDays($rule->intervalle_jours));

        if ($objet) {
            $query->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey());
        } elseif ($cleMeta) {
            $query->whereJsonContains('metadata->activite_code', $cleMeta);
        }

        return $query->exists();
    }
}
