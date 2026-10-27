<?php

namespace App\Services\Depense;

use App\Contracts\ConcernantStructureWorkflow;
use App\Models\AuditWorkflow;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use App\Models\WorkflowTache;
use App\Services\Workflow\AffectationTacheService;
use App\Services\Workflow\TacheActionService;
use App\Services\Workflow\TacheQueryService;
use App\Services\Workflow\TachePreparationService;
use App\Support\WorkflowObjetUrls;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Traçabilité intégrale et bidirectionnelle de la chaîne EB → ENG → LIQ → ORD → PAY.
 *
 * À partir de n'importe quel dossier : d'où il vient, où il se trouve, qui l'a traité en dernier, qui doit
 * le traiter maintenant et pour quelle action, depuis quand, ce qui reste à accomplir. Lecture seule : rien
 * n'est écrit ; toute information absente est signalée, jamais inventée. Les sources sont les données réelles :
 * instances et étapes de workflow, tâches « Mes Tâches », journal audit_workflow, dossiers et pièces.
 */
class TracabiliteDepenseService
{
    public const TYPES = [
        'besoin' => ExpressionBesoin::class,
        'engagement' => Engagement::class,
        'liquidation' => Liquidation::class,
        'ordonnancement' => Ordonnancement::class,
        'paiement' => Paiement::class,
    ];

    private const PROCESSUS = [
        ExpressionBesoin::class => ['EB', 'Expression de besoin'],
        Engagement::class => ['ENG', 'Engagement'],
        Liquidation::class => ['LIQ', 'Liquidation'],
        Ordonnancement::class => ['ORD', 'Ordonnancement'],
        Paiement::class => ['PAY', 'Paiement'],
    ];

    private const DECISIONS = ['valide' => 'Validation', 'rejete' => 'Rejet', 'renvoye' => 'Retour pour correction'];

    /** Fenêtre (secondes) en deçà de laquelle un démarrage de circuit suivant une décision amont est une transition automatique. */
    private const FENETRE_AUTOMATIQUE = 120;

    /** @var array<string, string> */
    private array $libellesRoles = [];

    public function __construct(
        private readonly AffectationTacheService $affectations,
        private readonly TacheQueryService $taches,
        private readonly TachePreparationService $preparation,
        private readonly DepenseQuotaService $quotas,
        private readonly SeuilOrdonnancementService $seuils,
    ) {}

    public static function typeDe(Model $objet): string
    {
        return (string) array_search($objet::class, self::TYPES, true);
    }

    /**
     * Reconstitution complète du dossier à partir de n'importe quelle de ses pièces.
     *
     * @return array<string, mixed>
     */
    public function dossier(Model $cible): array
    {
        $besoin = $this->besoinRacine($cible);
        $engagements = $besoin
            ? $besoin->engagements()->orderBy('id')->get()
            : collect([$this->engagementRacine($cible)])->filter();

        $noeuds = collect();
        if ($besoin) {
            $noeuds->push($this->noeud($besoin, 0, null));
        }
        foreach ($engagements as $eng) {
            $noeuds->push($this->noeud($eng, $besoin ? 1 : 0, $besoin));
            foreach ($eng->liquidations()->orderBy('id')->get() as $liq) {
                $noeuds->push($this->noeud($liq, $besoin ? 2 : 1, $eng));
                foreach ($liq->ordonnancements()->orderBy('id')->get() as $ord) {
                    $noeuds->push($this->noeud($ord, $besoin ? 3 : 2, $liq));
                    foreach ($ord->paiements()->orderBy('id')->get() as $pay) {
                        $noeuds->push($this->noeud($pay, $besoin ? 4 : 3, $ord));
                    }
                }
            }
        }
        if ($noeuds->doesntContain(fn ($n) => $n['objet']->is($cible))) {
            $noeuds->push($this->noeud($cible, 0, null));   // dossier non rattaché : signalé
        }

        $actions = $noeuds->filter(fn ($n) => $n['situation']['attendu'] !== null)->values();
        $focal = $noeuds->first(fn ($n) => $n['objet']->is($cible));

        return [
            'cible' => $cible,
            'focal' => $focal,
            'besoin' => $besoin,
            'noeuds' => $noeuds->values(),
            'en_attente' => $actions,
            'situation' => $actions->first()['situation'] ?? $noeuds->last()['situation'],
            'situation_dossier' => $actions->first() ?? $noeuds->last(),
            'historique' => $this->historique($noeuds),
            'anomalies' => $noeuds->flatMap(fn ($n) => collect($n['anomalies'])->map(fn ($a) => $a + ['dossier' => $n['numero'], 'processus' => $n['processus']]))->values(),
            'documents' => $this->documents($noeuds),
            'finances' => $this->finances($besoin, $engagements),
            'progression' => $this->progression($noeuds),
            'termine' => $actions->isEmpty() && $this->estSolde($noeuds),
        ];
    }

    // ── Relations de la chaîne (sens ascendant) ─────────────────────────────────────────────

    public function besoinRacine(Model $objet): ?ExpressionBesoin
    {
        return match (true) {
            $objet instanceof ExpressionBesoin => $objet,
            default => $this->engagementRacine($objet)?->expressionBesoin,
        };
    }

    public function engagementRacine(Model $objet): ?Engagement
    {
        return match (true) {
            $objet instanceof Engagement => $objet,
            $objet instanceof Liquidation => $objet->engagement,
            $objet instanceof Ordonnancement => $objet->liquidation?->engagement,
            $objet instanceof Paiement => $objet->ordonnancement?->liquidation?->engagement,
            default => null,
        };
    }

    // ── Nœud : un dossier de la chaîne et sa situation dans le workflow ─────────────────────

    /** @return array<string, mixed> */
    private function noeud(Model $objet, int $niveau, ?Model $parent): array
    {
        [$code, $libelle] = self::PROCESSUS[$objet::class];
        $situation = $this->situation($objet, $parent);
        if ($situation['attendu'] === null && $this->aUneSuite($objet)) {
            $situation['prochain'] = [];   // la suite existe déjà : elle est tracée sur les dossiers enfants
        }

        return [
            'objet' => $objet,
            'type' => self::typeDe($objet),
            'processus' => $code,
            'processus_libelle' => $libelle,
            'numero' => $objet->numero ?? '#'.$objet->getKey(),
            'niveau' => $niveau,
            'montant' => $this->montant($objet),
            'statut' => $objet->statut,
            'statut_libelle' => $this->statutLibelle($objet),
            'date' => $objet->created_at,
            'url' => WorkflowObjetUrls::show($objet),
            'url_tracabilite' => route('depense.tracabilite.show', [self::typeDe($objet), $objet->getKey()]),
            'situation' => $situation,
            'etapes' => $this->etapes($objet),
            'anomalies' => $this->anomalies($objet, $situation),
        ];
    }

    /**
     * Situation exacte du dossier dans le workflow.
     *
     * @return array<string, mixed>
     */
    public function situation(Model $objet, ?Model $parent = null): array
    {
        $instance = $objet->workflowInstances()->with(['workflow', 'etapes.acteur'])->latest('id')->first();
        $enCours = $instance && $instance->statut === 'en_cours' ? $instance : null;
        $etapeCourante = $enCours?->etapes->whereIn('ordre', $enCours->ordresDuGroupeCourant())->where('statut', 'en_attente')->sortBy('ordre')->first();
        $tache = WorkflowTache::query()->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())
            ->ouvertes()->with('assigne')->latest('id')->first();

        $dernier = $this->dernierActeur($objet, $parent);
        $attendu = $this->acteurAttendu($objet, $tache, $etapeCourante);
        $etat = match (true) {
            $objet->statut === 'a_corriger' => 'retourne',
            $objet->statut === 'rejete' && ! $tache => 'rejete',
            $tache?->statut === 'suspendue' => 'suspendu',
            $attendu !== null && ($attendu['manquante'] ?? false) => 'bloque',
            $tache?->statut === 'en_cours' => 'en_cours',
            $attendu !== null => 'en_attente',
            default => 'termine',
        };

        return [
            'instance' => $instance,
            'circuit' => $instance?->workflow?->libelle ?? $instance?->workflow?->code,
            'etape' => $etapeCourante ? [
                'ordre' => $etapeCourante->ordre,
                'code' => ($instance->workflow?->code ?? '').'#'.$etapeCourante->ordre,
                'libelle' => $etapeCourante->libelle ?: $this->libelleRole($etapeCourante->role),
                'role' => $etapeCourante->role,
                'entree_le' => $this->entreeEtape($enCours, $etapeCourante),
                'echeance_le' => $etapeCourante->echeance_le,
            ] : ($tache?->estPreparation() ? ['ordre' => 0, 'code' => 'PREPARATION#'.$tache->action_code, 'libelle' => $tache->libelle_etape, 'role' => $tache->role,
                'entree_le' => $tache->created_at, 'echeance_le' => $tache->echeance_le] : null),
            'etat' => $etat,
            'tache' => $tache,
            'dernier' => $dernier,
            'attendu' => $attendu,
            'prochain' => $this->prochain($objet, $enCours, $etapeCourante, $tache),
            'restantes' => $this->restantes($objet, $enCours, $etapeCourante),
        ];
    }

    /**
     * Dernier acteur métier : la dernière décision portée sur une étape ou la dernière soumission (démarrage de
     * circuit). Une soumission automatique (enchaînement de la chaîne) est signalée comme telle ; le dernier
     * acteur humain est alors conservé séparément. Les consultations ne comptent pas.
     *
     * @return array<string, mixed>|null
     */
    private function dernierActeur(Model $objet, ?Model $parent): ?array
    {
        $evenements = collect();
        foreach ($objet->workflowInstances()->with(['etapes.acteur', 'workflow'])->get() as $instance) {
            foreach ($instance->etapes->whereNotNull('decision_le')->whereNotNull('acteur_id') as $e) {
                $evenements->push(['date' => $e->decision_le, 'user' => $e->acteur, 'role' => $e->role, 'action' => (self::DECISIONS[$e->statut] ?? ucfirst((string) $e->statut)).' — '.($e->libelle ?: $this->libelleRole($e->role)),
                    'decision' => $e->statut, 'commentaire' => $e->observation, 'reference' => $e->hash_visa ? 'Visa '.substr($e->hash_visa, 0, 12) : null, 'automatique' => false]);
            }
            $evenements->push(['date' => $instance->created_at, 'user' => User::query()->find($instance->demarre_par), 'role' => null,
                'action' => 'Soumission au circuit « '.($instance->workflow?->libelle ?? $instance->workflow?->code).' »', 'decision' => 'soumis',
                'commentaire' => null, 'reference' => null, 'automatique' => $this->demarrageAutomatique($instance, $parent)]);
            // Un renvoi remet l'étape « à traiter » (décision effacée sur l'étape) : le journal append-only la conserve.
            $journal = AuditWorkflow::query()->where('workflow_instance_id', $instance->id)->whereIn('action', ['workflow_renvoye', 'workflow_rejete', 'workflow_valide'])->get();
            foreach ($journal as $ev) {
                $dejaVu = $evenements->contains(fn ($e) => $e['decision'] !== 'soumis' && ($e['user']?->id === $ev->acteur_id) && abs($e['date']->diffInSeconds($ev->created_at)) <= 5);
                if (! $dejaVu && $ev->acteur_id) {
                    $etape = $instance->etapes->firstWhere('ordre', $ev->etape_ordre);
                    $decision = str_replace('workflow_', '', (string) $ev->action);
                    $evenements->push(['date' => $ev->created_at, 'user' => User::query()->find($ev->acteur_id), 'role' => $etape?->role,
                        'action' => (self::DECISIONS[$decision] ?? ucfirst($decision)).' — '.($etape?->libelle ?: $this->libelleRole($etape?->role)),
                        'decision' => $decision, 'commentaire' => $ev->observation, 'reference' => null, 'automatique' => false]);
                }
            }
        }
        if ($evenements->isEmpty()) {
            $createur = User::query()->find($objet->demandeur_id ?? $objet->cree_par ?? $objet->created_by ?? null);
            $decision = $this->decisionParent($parent);
            $auto = $decision !== null && abs($objet->created_at->diffInSeconds($decision)) <= self::FENETRE_AUTOMATIQUE;
            if (! $createur && ! $auto) {
                return null;
            }
            $acteur = $this->formaterActeur(['date' => $objet->created_at, 'user' => $createur, 'role' => null,
                'action' => $auto ? 'Génération automatique à la validation de '.($parent->numero ?? 'l\'étape précédente') : 'Création du dossier',
                'decision' => 'cree', 'commentaire' => null, 'reference' => null, 'automatique' => $auto]);
            $acteur['dernier_humain'] = $auto && $parent ? $this->dernierActeur($parent, null) : null;

            return $acteur;
        }
        $tries = $evenements->sortByDesc(fn ($e) => ($e['date']?->timestamp ?? 0) * 10 + ($e['decision'] === 'soumis' ? 0 : 1))->values();   // à seconde égale, la décision suit la soumission
        $dernier = $this->formaterActeur($tries->first());
        $humain = $tries->first(fn ($e) => ! $e['automatique'] && $e['user']);
        $dernier['dernier_humain'] = $dernier['automatique'] && $humain ? $this->formaterActeur($humain) : null;

        return $dernier;
    }

    private function aUneSuite(Model $objet): bool
    {
        return match (true) {
            $objet instanceof ExpressionBesoin => $objet->engagements()->exists(),
            $objet instanceof Engagement => $objet->liquidations()->exists(),
            $objet instanceof Liquidation => $objet->ordonnancements()->exists(),
            $objet instanceof Ordonnancement => $objet->paiements()->exists(),
            default => false,
        };
    }

    /** Date de la dernière décision de validation du dossier parent (déclencheur des générations automatiques). */
    private function decisionParent(?Model $parent): ?CarbonInterface
    {
        if (! $parent || ! method_exists($parent, 'workflowInstances')) {
            return null;
        }
        $date = $parent->workflowInstances()->where('statut', 'valide')->latest('id')->first()?->etapes()->max('decision_le');

        return $date ? \Illuminate\Support\Carbon::parse($date) : null;
    }

    /** Démarrage enchaîné automatiquement à la décision du dossier parent (soumission auto de l'ENG, démarrage auto de la LIQ). */
    private function demarrageAutomatique(WorkflowInstance $instance, ?Model $parent): bool
    {
        $decision = $this->decisionParent($parent);

        return $decision !== null && abs($instance->created_at->diffInSeconds($decision)) <= self::FENETRE_AUTOMATIQUE;
    }

    /** @param array<string, mixed> $e */
    private function formaterActeur(array $e): array
    {
        /** @var User|null $u */
        $u = $e['user'];

        return [
            'nom' => $e['automatique'] ? 'Transition automatique — Système BUDGET-CEEAC' : ($u?->name ?? '—'),
            'pour_compte_de' => $e['automatique'] ? $u?->name : null,
            'user_id' => $u?->id,
            'matricule' => $u?->matricule,
            'fonction' => $u?->fonction?->libelle ?? $u?->poste?->libelle,
            'role' => $e['role'] ? $this->libelleRole($e['role']) : ($u ? $u->getRoleNames()->map(fn ($r) => $this->libelleRole($r))->implode(', ') : null),
            'structure' => $u?->structure?->libelle,
            'action' => $e['action'],
            'decision' => $e['decision'],
            'date' => $e['date'],
            'commentaire' => $e['commentaire'],
            'reference' => $e['reference'],
            'automatique' => $e['automatique'],
        ];
    }

    /**
     * Acteur actuellement attendu : l'affectation effective (tâche ouverte, prise en charge nominative ou
     * titulaires de la règle d'affectation). Aucun nom n'est inventé : sans titulaire, « affectation manquante ».
     *
     * @return array<string, mixed>|null
     */
    private function acteurAttendu(Model $objet, ?WorkflowTache $tache, ?WorkflowInstanceEtape $etape): ?array
    {
        $role = $tache?->role ?? $etape?->role;
        if (! $role) {
            return null;
        }
        $structureId = $objet instanceof ConcernantStructureWorkflow ? $objet->structureIdWorkflow() : ($objet->structure_id ?? null);
        $titulaires = $tache
            ? User::query()->where('actif', true)->get()->filter(fn (User $u) => $this->taches->peutTraiter($tache, $u))->values()
            : $this->affectations->titulaires($role, $structureId)['users'];
        // Porteurs du rôle écartés (séparation des fonctions, hors affectation) : motif exposé, jamais contourné.
        $exclus = $tache && $titulaires->isEmpty()
            ? $this->affectations->titulaires($role, $tache->structure_id)['users']
                ->map(fn (User $u) => ['nom' => $u->name, 'motif' => $this->taches->eligibilite($tache, $u)['motif']])->values()->all()
            : [];

        return [
            'role' => $this->libelleRole($role),
            'role_code' => $role,
            'nominatif' => $tache?->assigne ? ['nom' => $tache->assigne->name, 'fonction' => $tache->assigne->fonction?->libelle ?? $tache->assigne->poste?->libelle] : null,
            'titulaires' => $titulaires->map(fn (User $u) => ['nom' => $u->name, 'fonction' => $u->fonction?->libelle ?? $u->poste?->libelle, 'structure' => $u->structure?->code])->all(),
            'structure' => $tache?->structure?->libelle,
            'action' => $tache ? ($tache->estPreparation() ? $tache->libelle_etape : app(TacheActionService::class)->libelleValidation($tache).' — '.$tache->libelle_etape) : ($etape?->libelle ?? $this->libelleRole($role)),
            'regle' => $tache?->assigne ? 'Prise en charge nominative' : ($titulaires->count() > 1 ? 'Le premier titulaire disponible prend la tâche en charge' : null),
            'depuis' => $tache?->created_at ?? $etape?->updated_at,
            'echeance' => $tache?->echeance_le ?? $etape?->echeance_le,
            'en_retard' => (bool) $tache?->enRetard(),
            'tache' => $tache,
            'tache_statut' => $tache ? (WorkflowTache::STATUTS[$tache->statut] ?? $tache->statut) : null,
            'affectation_defaut' => (bool) $tache?->affectation_defaut,
            'manquante' => $titulaires->isEmpty(),
            'exclus' => $exclus,
        ];
    }

    /**
     * Prochain acteur prévu après l'action attendue : étape suivante du circuit, ou premier acteur du processus
     * suivant. Les branches conditionnelles (seuil d'ordonnancement, solde) sont calculées ou signalées.
     *
     * @return list<array<string, mixed>>
     */
    private function prochain(Model $objet, ?WorkflowInstance $instance, ?WorkflowInstanceEtape $etape, ?WorkflowTache $tache): array
    {
        if ($instance && $etape) {
            $suivante = $instance->etapes->where('ordre', '>', max($instance->ordresDuGroupeCourant()))->sortBy('ordre')->first();
            if ($suivante) {
                return [['etape' => $suivante->libelle ?: $this->libelleRole($suivante->role), 'role' => $this->libelleRole($suivante->role), 'processus' => self::PROCESSUS[$objet::class][0], 'condition' => 'Si l\'étape actuelle est validée.', 'certain' => true]];
            }

            return $this->processusSuivant($objet, 'Si l\'étape actuelle (dernière du circuit) est validée.');
        }
        if ($tache?->estPreparation()) {
            $circuit = $this->circuitPrevu($objet);

            return $circuit ? [['etape' => $circuit['premiere'], 'role' => $circuit['role'], 'processus' => self::PROCESSUS[$objet::class][0], 'condition' => 'Après soumission du dossier.', 'certain' => true]] : [];
        }
        if ($objet->statut === 'rejete' || in_array($objet->statut, ['annule'], true)) {
            return [];   // décision définitive : aucune étape inventée
        }

        return $this->processusSuivant($objet, null);
    }

    /** @return list<array<string, mixed>> */
    private function processusSuivant(Model $objet, ?string $condition): array
    {
        return match (true) {
            $objet instanceof ExpressionBesoin => [['etape' => 'Validation Direction du Budget', 'role' => $this->libelleRole('directeur'), 'processus' => 'ENG', 'condition' => $condition ?? 'Engagement généré et soumis automatiquement à la validation de l\'EB.', 'certain' => true]],
            $objet instanceof Engagement => [['etape' => 'Certification du service fait', 'role' => $this->libelleRole('chef_service'), 'processus' => 'LIQ', 'condition' => $condition ?? 'Liquidation générée au visa de l\'engagement.', 'certain' => true]],
            $objet instanceof Liquidation => $this->branchesOrdonnancement((float) $objet->montant, $condition),
            $objet instanceof Ordonnancement => [['etape' => 'Préparation du paiement', 'role' => $this->libelleRole('comptable'), 'processus' => 'PAY', 'condition' => $condition ?? 'Ordre de paiement émis à la signature.', 'certain' => true]],
            $objet instanceof Paiement => $this->apresPaiement($objet, $condition),
            default => [],
        };
    }

    /** Ordonnateur selon le seuil paramétré (lecture seule du paramètre ; montant de la liquidation). */
    private function branchesOrdonnancement(float $montant, ?string $condition): array
    {
        $snap = $this->seuils->snapshotPour($montant);
        $seuil = number_format((float) $snap['montant'], 0, ',', ' ');

        return [
            ['etape' => 'Soumission de l\'ordonnancement', 'role' => $this->libelleRole('gestionnaire_budgetaire'), 'processus' => 'ORD', 'condition' => $condition ?? 'Ordonnancement généré à la validation de la liquidation.', 'certain' => true],
            ['etape' => 'Signature de l\'ordonnancement', 'role' => $this->libelleRole($snap['ordonnateur_role']), 'processus' => 'ORD',
                'condition' => 'Montant '.number_format($montant, 0, ',', ' ').' XAF '.($snap['ordonnateur_role'] === 'president' ? '>' : '≤')." seuil paramétré ({$seuil} XAF).", 'certain' => true],
        ];
    }

    private function apresPaiement(Paiement $paiement, ?string $condition): array
    {
        if ($paiement->statut !== 'valide') {
            return [['etape' => 'Autorisation de décaissement', 'role' => $this->libelleRole('agent_comptable'), 'processus' => 'PAY', 'condition' => $condition ?? 'Après contrôles comptables.', 'certain' => true]];
        }
        $reste = $paiement->ordonnancement ? $this->quotas->montantRestantPaiement($paiement->ordonnancement) : 0;

        return $reste > 0.01
            ? [['etape' => 'Paiement du solde', 'role' => $this->libelleRole('comptable'), 'processus' => 'PAY', 'condition' => 'Solde restant : '.number_format($reste, 0, ',', ' ').' XAF.', 'certain' => true]]
            : [];
    }

    /** @return array{premiere: string, role: string}|null circuit qui s'appliquera à la soumission (sans écriture) */
    private function circuitPrevu(Model $objet): ?array
    {
        $code = match (true) {
            $objet instanceof Ordonnancement => $objet->ordonnateur_role
                ? ($objet->ordonnateur_role === 'president' ? 'ORDONNANCEMENT_PRESIDENT' : 'ORDONNANCEMENT')
                : $this->seuils->snapshotPour((float) $objet->montant)['circuit'],
            default => app(\App\Services\Workflow\CircuitPrevuService::class)->code($objet),
        };
        $workflow = $code ? \App\Models\Workflow::with(['etapes' => fn ($q) => $q->orderBy('ordre')])->where('code', $code)->where('actif', true)->first() : null;
        $premiere = $workflow?->etapes->first();

        return $premiere ? ['premiere' => $premiere->libelle, 'role' => $this->libelleRole($premiere->role)] : null;
    }

    /** @return list<array<string, mixed>> étapes restantes du circuit en cours (après l'étape actuelle) */
    private function restantes(Model $objet, ?WorkflowInstance $instance, ?WorkflowInstanceEtape $etape): array
    {
        if (! $instance || ! $etape) {
            return [];
        }

        return $instance->etapes->where('ordre', '>', $etape->ordre)->where('statut', 'en_attente')->sortBy('ordre')
            ->map(fn ($e) => ['ordre' => $e->ordre, 'libelle' => $e->libelle ?: $this->libelleRole($e->role), 'role' => $this->libelleRole($e->role)])->values()->all();
    }

    /**
     * Parcours des étapes de tous les circuits du dossier (y compris les circuits rejetés ou annulés) avec délais.
     *
     * @return list<array<string, mixed>>
     */
    private function etapes(Model $objet): array
    {
        $lignes = [];
        foreach ($objet->workflowInstances()->with(['workflow', 'etapes.acteur'])->orderBy('id')->get() as $instance) {
            $precedente = $instance->created_at;
            foreach ($instance->etapes->sortBy('ordre') as $e) {
                $reception = $e->statut === 'en_attente' && $instance->statut === 'en_cours' && (int) $instance->etape_courante !== (int) $e->ordre && ! in_array($e->ordre, $instance->ordresDuGroupeCourant(), true)
                    ? null : $this->entreeEtape($instance, $e, $precedente);
                $fin = $e->decision_le;
                $lignes[] = [
                    'circuit' => $instance->workflow?->libelle ?? $instance->workflow?->code,
                    'instance_statut' => $instance->statut,
                    'ordre' => $e->ordre,
                    'libelle' => $e->libelle ?: $this->libelleRole($e->role),
                    'role' => $this->libelleRole($e->role),
                    'statut' => $instance->statut === 'en_cours' && $e->statut === 'en_attente' && in_array($e->ordre, $instance->ordresDuGroupeCourant(), true) ? 'actuelle'
                        : ($e->statut === 'en_attente' ? ($instance->statut === 'en_cours' ? 'future' : 'non_atteinte') : $e->statut),
                    'acteur' => $e->acteur?->name,
                    'acteur_fonction' => $e->acteur?->fonction?->libelle ?? $e->acteur?->poste?->libelle,
                    'reception' => $reception,
                    'fin' => $fin,
                    'duree' => $reception ? $this->duree($reception, $fin ?? now()) : null,
                    'jours_ouvres' => $reception ? $this->joursOuvres($reception, $fin ?? now()) : null,
                    'echeance' => $e->echeance_le,
                    'retard' => $e->echeance_le && ($fin ?? now())->greaterThan($e->echeance_le),
                    'observation' => $e->observation,
                    'categorie_rejet' => $e->categorie_rejet,
                ];
                if ($fin) {
                    $precedente = $fin;
                }
            }
        }

        return $lignes;
    }

    /** Date d'entrée dans l'étape : fin de l'étape précédente du même circuit, sinon démarrage du circuit. */
    private function entreeEtape(WorkflowInstance $instance, WorkflowInstanceEtape $etape, ?CarbonInterface $precedente = null): ?CarbonInterface
    {
        if ($precedente) {
            return $precedente;
        }
        $avant = $instance->etapes->where('ordre', '<', $etape->ordre)->whereNotNull('decision_le')->max('decision_le');

        return $avant ? \Illuminate\Support\Carbon::parse($avant) : $instance->created_at;
    }

    private function duree(CarbonInterface $debut, CarbonInterface $fin): string
    {
        $minutes = (int) $debut->diffInMinutes($fin);
        $j = intdiv($minutes, 1440);
        $h = intdiv($minutes % 1440, 60);
        $m = $minutes % 60;

        return trim(($j ? "{$j} j " : '').($h ? "{$h} h " : '').(! $j ? "{$m} min" : ''));
    }

    private function joursOuvres(CarbonInterface $debut, CarbonInterface $fin): int
    {
        $n = 0;
        for ($d = $debut->copy()->startOfDay(); $d->lt($fin->copy()->startOfDay()); $d->addDay()) {
            if (! $d->isWeekend()) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Blocages et incohérences détectés (aucune correction automatique).
     *
     * @param  array<string, mixed>  $s
     * @return list<array{gravite: string, code: string, message: string}>
     */
    private function anomalies(Model $objet, array $s): array
    {
        $a = [];
        $instance = $s['instance'];
        $tache = $s['tache'];
        $enCours = $instance && $instance->statut === 'en_cours';

        if ($enCours && $s['etape'] && ! $tache && ! ($objet instanceof \App\Contracts\RenvoiAuDemandeur && $objet->enCorrection())) {
            $a[] = ['gravite' => 'bloquante', 'code' => 'tache_manquante', 'message' => 'Étape en attente sans tâche active : invisible dans « Mes Tâches ».'];
        }
        $attendue = $this->preparation->actionAttendue($objet);
        if (! $enCours && $attendue && (! $tache || $tache->action_code !== $attendue['code'])) {
            $a[] = ['gravite' => 'bloquante', 'code' => 'tache_manquante', 'message' => "Action attendue (« {$attendue['libelle']} ») sans tâche active : lancer la synchronisation des tâches de préparation."];
        }
        if ($s['attendu'] && $s['attendu']['manquante']) {
            $a[] = ['gravite' => 'bloquante', 'code' => 'affectation_manquante', 'message' => 'Affectation manquante — Intervention administrative requise : aucun utilisateur actif ne peut traiter l\'étape « '.$s['attendu']['role'].' ».'
                .collect($s['attendu']['exclus'])->map(fn ($e) => " {$e['nom']} est écarté : {$e['motif']}")->implode('')];
        }
        if ($tache?->assigne && ! $tache->assigne->actif) {
            $a[] = ['gravite' => 'bloquante', 'code' => 'affectataire_inactif', 'message' => "Tâche prise en charge par un utilisateur inactif ({$tache->assigne->name}) : réaffectation requise."];
        }
        if ($tache?->affectation_defaut) {
            $a[] = ['gravite' => 'avertissement', 'code' => 'affectation_par_defaut', 'message' => 'Aucun titulaire organique paramétré : tâche ouverte à tous les porteurs du rôle.'];
        }
        if ($tache?->enRetard()) {
            $a[] = ['gravite' => 'majeure', 'code' => 'retard', 'message' => 'Échéance dépassée depuis '.$this->duree($tache->echeance_le, now()).'.'];
        }
        if ($tache && $s['attendu'] && ! $s['attendu']['manquante']) {
            $notifie = \Illuminate\Support\Facades\DB::table('notifications')->where('ressource_type', $objet->getMorphClass())->where('ressource_id', $objet->getKey())
                ->where('action_statut', 'en_attente')->exists();
            if (! $notifie) {
                $a[] = ['gravite' => 'majeure', 'code' => 'notification_manquante', 'message' => 'Action attendue sans notification d\'action en attente chez les titulaires.'];
            }
        }
        if (in_array($objet->statut, ['soumis'], true) && ! $enCours) {
            $a[] = ['gravite' => 'bloquante', 'code' => 'transition_non_finalisee', 'message' => 'Dossier « soumis » sans circuit en cours : incohérence entre statut et workflow.'];
        }
        if ($objet instanceof Liquidation && $objet->statut === 'valide' && $objet->ordonnancements()->doesntExist()) {
            $a[] = ['gravite' => 'majeure', 'code' => 'rattachement_manquant', 'message' => 'Liquidation validée sans ordonnancement rattaché.'];
        }
        if ($objet instanceof Ordonnancement && $objet->statut === 'valide' && $objet->paiements()->doesntExist()) {
            $a[] = ['gravite' => 'majeure', 'code' => 'rattachement_manquant', 'message' => 'Ordonnancement signé sans paiement rattaché.'];
        }
        if (! ($objet instanceof ExpressionBesoin) && ! $this->besoinRacine($objet)) {
            $a[] = ['gravite' => 'avertissement', 'code' => 'origine_absente', 'message' => 'Aucune expression de besoin d\'origine rattachée.'];
        }

        return $a;
    }

    /**
     * Historique chronologique de la chaîne : créations, soumissions et décisions (journal audit_workflow).
     *
     * @param  Collection<int, array<string, mixed>>  $noeuds
     * @return list<array<string, mixed>>
     */
    private function historique(Collection $noeuds): array
    {
        $lignes = collect();
        foreach ($noeuds as $n) {
            $o = $n['objet'];
            $createur = User::query()->find($o->demandeur_id ?? $o->cree_par ?? $o->created_by ?? null);
            $lignes->push(['date' => $o->created_at, 'processus' => $n['processus'], 'dossier' => $n['numero'], 'etape' => 'Création', 'action' => 'Création du dossier',
                'acteur' => $createur?->name ?? 'Système', 'fonction' => $createur?->fonction?->libelle, 'role' => null, 'structure' => $createur?->structure?->code,
                'statut_avant' => null, 'statut_apres' => null, 'destinataire' => null, 'commentaire' => null, 'reference' => null]);
            foreach (AuditWorkflow::query()->where('objet_type', $o->getMorphClass())->where('objet_id', $o->getKey())->orderBy('created_at')->orderBy('id')->get() as $ev) {
                $acteur = $ev->acteur_id ? User::query()->with(['fonction', 'structure'])->find($ev->acteur_id) : null;
                $meta = is_array($ev->meta) ? $ev->meta : (json_decode((string) $ev->meta, true) ?: []);
                $lignes->push([
                    'date' => $ev->created_at, 'processus' => $n['processus'], 'dossier' => $n['numero'],
                    'etape' => $meta['etape'] ?? ($ev->etape_ordre ? 'Étape '.$ev->etape_ordre : $ev->workflow_code),
                    'action' => $this->libelleAction((string) $ev->action),
                    'acteur' => $acteur?->name ?? 'Système', 'fonction' => $acteur?->fonction?->libelle ?? $acteur?->poste?->libelle,
                    'role' => isset($meta['role']) ? $this->libelleRole($meta['role']) : null, 'structure' => $acteur?->structure?->code,
                    'statut_avant' => $meta['statut_avant'] ?? null, 'statut_apres' => $meta['statut_apres'] ?? null,
                    'destinataire' => $meta['destinataire'] ?? null, 'commentaire' => $ev->observation,
                    'reference' => isset($meta['pour_compte_de']) ? 'Pour le compte de #'.$meta['pour_compte_de'] : null,
                ]);
            }
        }

        return $lignes->sortBy(fn ($l) => $l['date']?->getTimestamp() ?? 0)->values()->map(fn ($l, $i) => ['n' => $i + 1] + $l)->all();
    }

    private function libelleAction(string $action): string
    {
        return match ($action) {
            'workflow_demarre' => 'Soumission au circuit',
            'workflow_valide' => 'Validation',
            'workflow_rejete' => 'Rejet',
            'workflow_renvoye' => 'Retour pour correction',
            'workflow_repris' => 'Resoumission après correction',
            default => ucfirst(str_replace(['workflow_', '_'], ['', ' '], $action)),
        };
    }

    /** @return list<array<string, mixed>> */
    private function documents(Collection $noeuds): array
    {
        $docs = [];
        foreach ($noeuds as $n) {
            $o = $n['objet'];
            if (method_exists($o, 'pieces')) {
                foreach ($o->pieces()->with('auteur')->get() as $p) {
                    $docs[] = ['processus' => $n['processus'], 'dossier' => $n['numero'], 'type' => $p->categorie ?? 'pièce', 'nom' => $p->nom, 'auteur' => $p->auteur?->name,
                        'date' => $p->created_at, 'url' => route('pieces.download', $p), 'piece' => $p];
                }
            }
            if (method_exists($o, 'gedDocuments')) {
                foreach ($o->gedDocuments()->get() as $g) {
                    $docs[] = ['processus' => $n['processus'], 'dossier' => $n['numero'], 'type' => 'GED', 'nom' => $g->titre ?? $g->reference ?? 'Document GED',
                        'auteur' => null, 'date' => $g->created_at, 'url' => null, 'piece' => null];
                }
            }
        }

        return $docs;
    }

    /**
     * Situation financière : montants par processus (sans double comptage) et lignes budgétaires imputées.
     *
     * @param  Collection<int, Engagement>  $engagements
     * @return array<string, mixed>
     */
    private function finances(?ExpressionBesoin $besoin, Collection $engagements): array
    {
        $eng = $liq = $ord = $pay = 0.0;
        $lignes = [];
        foreach ($engagements as $e) {
            if (in_array($e->statut, ['rejete', 'annule'], true)) {
                continue;
            }
            $eng += (float) $e->montant_total;
            $liq += $this->quotas->montantLiquideEngagement($e);
            foreach ($e->liquidations as $l) {
                foreach ($l->ordonnancements()->whereNotIn('statut', ['rejete', 'annule'])->get() as $o) {
                    $ord += (float) $o->montant;
                    $pay += (float) $o->paiements()->where('statut', 'valide')
                        ->where(fn ($q) => $q->whereNull('etat_execution')->orWhere('etat_execution', '!=', 'instrument_rejete'))->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(montant_xaf, montant)'));
                }
            }
            foreach ($e->imputations()->with('ligneBudgetaire')->get() as $imp) {
                $lb = $imp->ligneBudgetaire;
                if ($lb) {
                    $lignes[$lb->id] = ['code' => $lb->code, 'intitule' => $lb->intitule, 'ouvert' => (float) ($lb->montant_revise ?? $lb->montant_ouvert),
                        'engage' => (float) $lb->montant_engage, 'liquide' => (float) $lb->montant_liquide, 'paye' => (float) $lb->montant_paye,
                        'disponible' => (float) ($lb->montant_revise ?? $lb->montant_ouvert) - (float) $lb->montant_engage,
                        'part_dossier' => ($lignes[$lb->id]['part_dossier'] ?? 0) + (float) $imp->montant];
                }
            }
        }

        return [
            'besoin' => $besoin ? (float) $besoin->montant_estime : null,
            'engage' => $eng, 'liquide' => $liq, 'ordonnance' => $ord, 'paye' => $pay,
            'reste_a_liquider' => max(0, $eng - $liq), 'reste_a_payer' => max(0, $ord - $pay),
            'lignes' => array_values($lignes),
        ];
    }

    /** @return array<string, string> état de chaque processus : fait | en_cours | a_venir */
    private function progression(Collection $noeuds): array
    {
        $etat = [];
        foreach (['EB', 'ENG', 'LIQ', 'ORD', 'PAY'] as $p) {
            $liste = $noeuds->where('processus', $p);
            $etat[$p] = match (true) {
                $liste->isEmpty() => 'a_venir',
                $liste->contains(fn ($n) => $n['situation']['attendu'] !== null) => 'en_cours',
                $liste->contains(fn ($n) => in_array($n['statut'], ['valide', 'vise', 'converti'], true)) => 'fait',
                $liste->contains(fn ($n) => $n['statut'] === 'rejete') => 'rejete',
                default => 'en_cours',
            };
        }

        return $etat;
    }

    private function estSolde(Collection $noeuds): bool
    {
        $pays = $noeuds->where('processus', 'PAY');

        return $pays->isNotEmpty() && $noeuds->where('processus', 'ORD')->every(fn ($n) => $this->quotas->montantRestantPaiement($n['objet']) <= 0.01);
    }

    private function montant(Model $o): float
    {
        return (float) ($o->montant_total ?? $o->montant_xaf ?? $o->montant ?? $o->montant_estime ?? 0);
    }

    private function statutLibelle(Model $o): string
    {
        return [
            'brouillon' => 'Brouillon', 'soumis' => 'En validation', 'a_corriger' => 'Retourné pour correction', 'valide' => 'Validé', 'vise' => 'Visé',
            'rejete' => 'Rejeté', 'converti' => 'Converti', 'en_attente_arbitrage' => 'En attente d\'arbitrage', 'annule' => 'Annulé',
        ][$o->statut] ?? ucfirst(str_replace('_', ' ', (string) $o->statut));
    }

    public function libelleRole(?string $role): string
    {
        if (! $role) {
            return '—';
        }

        return $this->libellesRoles[$role] ??= (string) (Role::query()->where('name', $role)->value('libelle') ?: str_replace('_', ' ', $role));
    }
}
