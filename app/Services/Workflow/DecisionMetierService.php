<?php

namespace App\Services\Workflow;

use App\Models\Engagement;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Depense\RibControleService;
use App\Services\Notifications\DepenseNotificationService;
use App\Services\WebhookService;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Décision métier unique sur un dossier en circuit : contrôles préalables, moteur de workflow,
 * effets métier. Partagé par les écrans des modules (Blade et API), l'API workflow générique et
 * « Mes Tâches », pour qu'aucun point d'entrée ne contourne un contrôle (ex. double contrôle du RIB).
 */
class DecisionMetierService
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly WebhookService $webhooks,
    ) {}

    /**
     * @param  array{decision: string, observation?: ?string, categorie_rejet?: ?string}  $data
     *
     * @throws RuntimeException
     */
    public function decider(Model $objet, User $user, array $data, ?int $etapeAttendueId = null): void
    {
        $instance = $objet->workflowActif ?? null;
        if (! $instance) {
            throw new RuntimeException('Aucun circuit actif.');
        }

        $decision = $data['decision'];
        $etapeRole = $instance->etapePourUtilisateur($user)?->role ?? $instance->etapeCourante()?->role;

        if ($objet instanceof Paiement && $decision === 'valider') {
            app(RibControleService::class)->assertValidationIndependante($objet, $user, $instance);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($objet, $instance, $user, $decision, $data, $etapeAttendueId, $etapeRole) {
            // Procédure LIQ § 3.4 : à l'étape de certification, le service initiateur constate le service fait
            // (constat obligatoire, enregistré avec la décision) ; le visa du CF exige ensuite cette certification.
            if ($objet instanceof \App\Models\Liquidation && $decision === 'valider' && $etapeRole !== 'controleur_financier') {
                $serviceFait = app(\App\Services\Depense\ServiceFaitService::class);
                if (! $serviceFait->estCertifie($objet)) {
                    $constat = trim((string) ($data['observation'] ?? ''));
                    if ($constat === '') {
                        throw new RuntimeException('Pour certifier, décrivez le service fait constaté (livraison, mission, travaux) dans l\'observation.');
                    }
                    $serviceFait->enregistrer($objet, ['service_fait' => $constat, 'service_fait_type' => 'constat'], $user);
                }
            }

            $this->workflow->traiterDecision(
                $instance,
                $user,
                $decision,
                $data['observation'] ?? null,
                $data['categorie_rejet'] ?? null,
                $etapeAttendueId,
            );
        });

        $this->apresDecision($objet->fresh() ?? $objet, $user, $decision, $data['observation'] ?? null, $etapeRole);
    }

    private function apresDecision(Model $objet, User $user, string $decision, ?string $observation, ?string $etapeRole): void
    {
        if ($objet instanceof Engagement && $decision === 'valider') {
            // Le contrôleur financier est celui qui vise, pas le valideur de la Direction du Budget.
            if ($etapeRole === 'controleur_financier') {
                $objet->update(['controleur_id' => $user->id]);
            }
            $this->webhooks->declencher('engagement.vise', [
                'numero' => $objet->numero,
                'montant' => (float) $objet->montant_total,
                'objet' => $objet->objet,
            ]);

            return;
        }

        $type = match (true) {
            $objet instanceof Liquidation => 'liquidation',
            $objet instanceof Ordonnancement => 'ordonnancement',
            $objet instanceof Paiement => 'paiement',
            default => null,
        };
        if ($type === null) {
            return;
        }

        if ($decision === 'valider') {
            $objet->update(['valide_par' => $user->id]);
            $this->webhooks->declencher("{$type}.valide", [
                'numero' => $objet->numero,
                'montant' => (float) $objet->montant,
            ]);
            if ($objet instanceof Paiement) {
                app(DepenseNotificationService::class)->notifierPaiementValide($objet->fresh());
            }
        } elseif ($objet instanceof Paiement) {
            $objet->rejets()->create([
                'type' => 'workflow',
                'motif' => Str::limit($observation ?? 'Rejet', 255, ''),
                'observation' => $observation,
                'rejete_par' => $user->id,
                'rejete_le' => now(),
            ]);
        }
    }
}
