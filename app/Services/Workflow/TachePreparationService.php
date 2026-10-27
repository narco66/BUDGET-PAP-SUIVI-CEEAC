<?php

namespace App\Services\Workflow;

use App\Contracts\ConcernantStructureWorkflow;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Services\Depense\DepenseQuotaService;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationCriticite;
use App\Support\NotificationNature;
use App\Support\WorkflowObjetUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tâches de préparation de la chaîne de dépense : actions humaines attendues hors étape de circuit
 * (soumettre, corriger, préparer, solder). Elles sont déduites de l'état réel du dossier — jamais
 * d'une donnée figée — et se clôturent d'elles-mêmes quand le dossier avance.
 *
 * La synchronisation est idempotente (index unique partiel) et s'exécute après validation de la
 * transaction métier ; la commande taches:synchroniser-preparation rejoue la projection.
 */
class TachePreparationService
{
    /** @var list<class-string<Model>> */
    public const MODELES = [ExpressionBesoin::class, Engagement::class, Liquidation::class, Ordonnancement::class, Paiement::class];

    private const MODULES = [
        ExpressionBesoin::class => 'EB',
        Engagement::class => 'ENG',
        Liquidation::class => 'LIQ',
        Ordonnancement::class => 'ORD',
        Paiement::class => 'PAY',
    ];

    public function __construct(
        private readonly AffectationTacheService $affectations,
        private readonly TacheWorkflowService $taches,
        private readonly DepenseQuotaService $quotas,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /** Programme la synchronisation du dossier (et des dossiers dont il conditionne l'action) après validation. */
    public function planifier(Model $objet): void
    {
        $cibles = [$objet];
        if ($objet instanceof Paiement && $objet->ordonnancement_id) {
            $cibles[] = Ordonnancement::query()->find($objet->ordonnancement_id);   // solde restant à payer
        }
        if ($objet instanceof Liquidation && $objet->engagement_id) {
            $cibles[] = Engagement::query()->find($objet->engagement_id);           // reliquat à liquider
        }
        foreach (array_filter($cibles) as $cible) {
            $classe = $cible::class;
            $id = $cible->getKey();
            DB::afterCommit(fn () => $this->synchroniserParCle($classe, $id));
        }
    }

    /** @param class-string<Model> $classe */
    public function synchroniserParCle(string $classe, int|string $id): void
    {
        try {
            $this->synchroniser($classe, $id);
        } catch (\Throwable $e) {
            report($e);   // la projection est rejouable : un échec ne doit pas annuler l'opération métier
        }
    }

    /**
     * Aligne les tâches de préparation ouvertes du dossier sur l'action réellement attendue.
     *
     * @param  class-string<Model>  $classe
     * @return array{creee: ?WorkflowTache, closes: int}
     */
    public function synchroniser(string $classe, int|string $id): array
    {
        return DB::transaction(function () use ($classe, $id) {
            $objet = $classe::query()->find($id);
            $morph = (new $classe)->getMorphClass();
            $attendue = $objet ? $this->actionAttendue($objet) : null;

            $closes = 0;
            $ouvertes = WorkflowTache::query()->where('nature', WorkflowTache::NATURE_PREPARATION)
                ->where('objet_type', $morph)->where('objet_id', $id)->ouvertes()->lockForUpdate()->get();
            foreach ($ouvertes as $tache) {
                if ($attendue && $tache->action_code === $attendue['code']) {
                    continue;
                }
                // Le dossier a avancé (action réalisée) ou a disparu (action sans objet).
                $this->clore($tache, $objet ? 'terminee' : 'annulee');
                $closes++;
            }

            $creee = null;
            if ($attendue && ! $ouvertes->contains('action_code', $attendue['code'])) {
                $creee = $this->creer($objet, $attendue);
            }

            return ['creee' => $creee, 'closes' => $closes];
        });
    }

    /**
     * Action humaine attendue sur le dossier, déduite de son état (null si aucune ou si une étape de
     * circuit est en cours — l'action relève alors des tâches de validation).
     *
     * @return array{code: string, libelle: string, role: string}|null
     */
    public function actionAttendue(Model $objet): ?array
    {
        $enCircuit = method_exists($objet, 'workflowInstances') && $objet->workflowInstances()->where('statut', 'en_cours')->exists();
        $action = fn (string $code, string $libelle, string $role) => compact('code', 'libelle', 'role');

        return match (true) {
            $objet instanceof ExpressionBesoin => match (true) {
                // Retour pour correction : le circuit reste « en cours » mais attend le demandeur.
                $objet->statut === 'a_corriger' => $action('eb_corriger', 'Expression de besoin retournée : à corriger et resoumettre', 'chef_service'),
                $objet->statut === 'brouillon' && ! $enCircuit && ! $this->demandeurPeutSoumettre($objet)
                    => $action('eb_soumettre', 'Expression de besoin préparée : à vérifier et soumettre', 'chef_service'),
                default => null,
            },
            $enCircuit => null,
            $objet instanceof Engagement => match ($objet->statut) {
                'brouillon' => $action('eng_soumettre', 'Engagement généré : à soumettre (réservation des crédits)', 'gestionnaire_budgetaire'),
                'en_attente_arbitrage' => $action('eng_arbitrage', 'Engagement bloqué (crédits insuffisants) : à reprendre après régularisation', 'gestionnaire_budgetaire'),
                'rejete' => $action('eng_corriger', 'Engagement rejeté : à corriger et resoumettre', 'gestionnaire_budgetaire'),
                'vise' => $this->reliquatALiquider($objet) ? $action('liq_reliquat', 'Reliquat d\'engagement : à liquider ou à dégager', 'gestionnaire_budgetaire') : null,
                default => null,
            },
            $objet instanceof Liquidation => match ($objet->statut) {
                'brouillon' => $action('liq_soumettre', 'Liquidation : à compléter (service fait, facture) et soumettre', 'gestionnaire_budgetaire'),
                // Rejet remplacé par une autre liquidation couvrant l'engagement : plus rien à corriger.
                'rejete' => $objet->engagement && $this->quotas->montantRestantLiquidation($objet->engagement) > 0.01
                    ? $action('liq_corriger', 'Liquidation rejetée : à corriger et resoumettre', 'gestionnaire_budgetaire') : null,
                default => null,
            },
            $objet instanceof Ordonnancement => match ($objet->statut) {
                'brouillon' => $action('ord_soumettre', 'Ordonnancement : à soumettre à la signature de l\'ordonnateur', 'gestionnaire_budgetaire'),
                'rejete' => $action('ord_corriger', 'Ordonnancement rejeté : à corriger et resoumettre', 'gestionnaire_budgetaire'),
                'valide' => $this->soldeAPayer($objet) ? $action('pay_solde', 'Solde restant à payer sur l\'ordonnancement', 'comptable') : null,
                default => null,
            },
            $objet instanceof Paiement => match ($objet->statut) {
                'brouillon' => $action('pay_preparer', 'Paiement à préparer (mode, RIB, pièces) et soumettre', 'comptable'),
                // Rejet remplacé par un autre paiement soldant l'ordonnancement : plus rien à corriger.
                'rejete' => $objet->ordonnancement && $this->quotas->montantRestantPaiement($objet->ordonnancement) > 0.01
                    ? $action('pay_corriger', 'Paiement rejeté : à corriger et resoumettre', 'comptable') : null,
                default => null,
            },
            default => null,
        };
    }

    /** Le demandeur peut-il soumettre lui-même ? Sinon, la soumission revient au chef de service (préparation déléguée). */
    private function demandeurPeutSoumettre(ExpressionBesoin $besoin): bool
    {
        $demandeur = $besoin->demandeur_id ? User::query()->find($besoin->demandeur_id) : null;

        return $demandeur !== null && $demandeur->can('besoin.modifier');
    }

    private function reliquatALiquider(Engagement $engagement): bool
    {
        // Une liquidation rejetée porte sa propre tâche de correction.
        return $this->quotas->montantRestantLiquidation($engagement) > 0.01
            && ! $engagement->liquidations()->where('statut', 'rejete')->exists();
    }

    private function soldeAPayer(Ordonnancement $ordonnancement): bool
    {
        // Les paiements en préparation ou soumis consomment déjà le reliquat ; un paiement rejeté porte sa tâche.
        return $this->quotas->montantRestantPaiement($ordonnancement) > 0.01
            && ! $ordonnancement->paiements()->where('statut', 'rejete')->exists();
    }

    /** @param array{code: string, libelle: string, role: string} $attendue */
    private function creer(Model $objet, array $attendue): ?WorkflowTache
    {
        $structureId = $objet instanceof ConcernantStructureWorkflow ? $objet->structureIdWorkflow() : ($objet->structure_id ?? null);
        $affectation = $this->affectations->titulaires($attendue['role'], $structureId);

        try {
            $tache = DB::transaction(fn () => WorkflowTache::create([
                'reference' => 'TMP-'.\Illuminate\Support\Str::random(24),
                'nature' => WorkflowTache::NATURE_PREPARATION,
                'action_code' => $attendue['code'],
                'workflow_code' => 'PREPARATION',
                'module' => self::MODULES[$objet::class] ?? 'AUTRE',
                'objet_type' => $objet->getMorphClass(),
                'objet_id' => $objet->getKey(),
                'objet_libelle' => TacheWorkflowService::libelleObjet($objet),
                'structure_id' => $structureId,
                'etape_ordre' => 0,
                'role' => $attendue['role'],
                'affectation_defaut' => $affectation['defaut'],
                'libelle_etape' => $attendue['libelle'],
                'statut' => 'a_traiter',
                'echeance_le' => now()->addDays(max(1, (int) config('workflow.taches.delai_preparation_jours', 3))),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;   // créée par une synchronisation concurrente
        }
        $tache->update(['reference' => sprintf('TACHE-%d-%06d', (int) $tache->created_at->format('Y'), $tache->id)]);
        $this->taches->journaliser($tache, 'creation', null, $affectation['defaut'] ? 'Aucun titulaire organique : affectation par défaut au rôle.' : null,
            ['action' => $attendue['code'], 'role' => $attendue['role'], 'affectation_defaut' => $affectation['defaut']]);
        $this->notifier($tache, $objet, $affectation['users']);

        return $tache;
    }

    private function clore(WorkflowTache $tache, string $statut): void
    {
        $tache->update([
            'statut' => $statut,
            'decision' => $statut === 'terminee' ? 'traitee' : null,
            'termine_le' => now(),
            'version' => $tache->version + 1,
        ]);
        $this->taches->journaliser($tache, $statut === 'terminee' ? 'decision' : 'annulation', null,
            $statut === 'terminee' ? 'Action réalisée : le dossier a avancé.' : 'Dossier supprimé : action sans objet.');
        $this->taches->cloreNotifications($tache, null, $statut === 'terminee' ? NotificationNature::ACTION_TRAITEE : NotificationNature::ACTION_ANNULEE);
    }

    /**
     * Notifie les titulaires. Une notification d'action métier déjà ouverte sur le dossier (ex. « paiement
     * à préparer ») est rattachée à la tâche plutôt que doublée ; elle se clôt avec elle.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $destinataires
     */
    private function notifier(WorkflowTache $tache, Model $objet, $destinataires): void
    {
        $nouveaux = collect();
        foreach ($destinataires as $user) {
            $existante = DB::table('notifications')->where('notifiable_id', $user->id)
                ->where('ressource_type', $objet->getMorphClass())->where('ressource_id', $objet->getKey())
                ->where('action_statut', NotificationNature::ACTION_EN_ATTENTE)->where('type_code', 'not like', 'workflow.%')
                ->first(['id', 'data']);
            if ($existante) {
                $data = json_decode((string) $existante->data, true) ?: [];
                $data['meta'] = ($data['meta'] ?? []) + ['tache_id' => $tache->id];
                DB::table('notifications')->where('id', $existante->id)->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            } else {
                $nouveaux->push($user);
            }
        }
        if ($nouveaux->isEmpty()) {
            return;
        }

        try {
            // Point de sauvegarde : une panne de notification n'annule pas la tâche (la tâche ne dépend pas de la notification).
            DB::transaction(fn () => $this->dispatcher->envoyer(
                $nouveaux,
                'workflow.etape_en_attente',
                ($objet->numero ?? WorkflowObjetUrls::libelle($objet)).' — action requise',
                "{$tache->libelle_etape} — tâche {$tache->reference}.",
                route('taches.show', $tache),
                'workflow',
                NotificationCriticite::ATTENTION,
                meta: ['tache_id' => $tache->id, 'action_code' => $tache->action_code],
                canaux: config('notifications.email.enabled', true) ? ['database', 'email'] : ['database'],
                ressource: $objet,
                actionRequise: true,
                cleDeduplication: "prep:{$tache->id}",
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
