<?php



namespace App\Services\Budget;



use App\Models\Budget;

use App\Models\BudgetConsolidation;

use App\Models\BudgetPreparation;

use App\Models\BudgetRevision;

use App\Models\BudgetSimulation;

use App\Models\Exercice;

use App\Models\LigneBudgetaire;

use Illuminate\Support\Facades\DB;



/** Rapport de synthèse du cycle budgétaire institutionnel (phase B5). */

class BudgetReportService

{

    public function __construct(

        private readonly BudgetAuditService $audit,

        private readonly BudgetEnveloppeService $enveloppe,

        private readonly BudgetPluriannuelService $pluriannuel,

    ) {

    }



    /** @return array<string,mixed> */

    public function synthese(?int $exerciceId = null): array

    {

        $exercice = $exerciceId

            ? Exercice::find($exerciceId)

            : ExerciceService::courant();



        if (! $exercice) {

            return ['erreur' => 'Aucun exercice disponible.'];

        }



        $budget = Budget::with(['programmationPluriannuelle', 'programmationAnnuelle'])

            ->firstWhere('exercice_id', $exercice->id);



        $lignes = LigneBudgetaire::where('exercice_id', $exercice->id)->get();

        $audit = $this->audit->executer($exercice->id);



        $preparations = $budget

            ? BudgetPreparation::where('budget_id', $budget->id)->get()

            : collect();



        $rapport = [

            'genere_le' => now()->toIso8601String(),

            'exercice' => ['id' => $exercice->id, 'annee' => $exercice->annee, 'statut' => $exercice->statut],

            'enveloppe' => $budget ? [

                'id' => $budget->id,

                'reference' => $budget->reference,

                'statut' => $budget->statut,

                'version' => $budget->version,

                'montant_total' => (float) $budget->montant_total,

                'adopte_le' => $budget->adopte_le?->toIso8601String(),

            ] : null,

            'montants' => [

                'ouvert' => (float) $lignes->sum('montant_ouvert'),

                'revise' => (float) $lignes->sum(fn ($l) => (float) ($l->montant_revise ?? $l->montant_ouvert)),

                'engage' => (float) $lignes->sum('montant_engage'),

                'paye' => (float) $lignes->sum('montant_paye'),

                'lignes' => $lignes->count(),

            ],

            'preparation' => [

                'total' => $preparations->count(),

                'par_statut' => $preparations->countBy('statut')->all(),

                'montant_demande' => (float) $preparations->sum('montant_demande'),

                'montant_arbitre' => (float) $preparations->sum(fn ($p) => (float) ($p->montant_arbitre ?? 0)),

            ],

            'consolidations' => $budget ? [

                'count' => BudgetConsolidation::where('budget_id', $budget->id)->count(),

                'derniere_version' => (int) BudgetConsolidation::where('budget_id', $budget->id)->max('version'),

            ] : ['count' => 0, 'derniere_version' => 0],

            'simulations' => $budget ? [

                'count' => BudgetSimulation::where('budget_id', $budget->id)->count(),

                'retenue' => BudgetSimulation::where('budget_id', $budget->id)->where('statut', 'retenu')->value('code'),

            ] : ['count' => 0, 'retenue' => null],

            'revisions' => $budget ? [

                'count' => BudgetRevision::where('budget_id', $budget->id)->where('statut', 'valide')->count(),

                'rectificatifs' => BudgetRevision::where('budget_id', $budget->id)->where('type', 'rectificatif')->where('statut', 'valide')->count(),

                'virements' => BudgetRevision::where('budget_id', $budget->id)->where('type', 'virement')->where('statut', 'valide')->count(),

            ] : ['count' => 0, 'rectificatifs' => 0, 'virements' => 0],

            'pluriannuel' => $budget && $budget->programmation_pluriannuelle_id

                ? $this->pluriannuel->comparer($budget)

                : null,

            'audit' => [

                'anomalies_total' => $audit['anomalies_total'],

                'cycle_institutionnel' => $audit['cycle_institutionnel'] ?? [],

            ],

            'conformite' => $this->conformite($budget, $audit, $preparations->count()),

        ];



        return $rapport;

    }



    /** @param  array<string,mixed>  $audit */

    /** @return array<string,mixed> */

    private function conformite(?Budget $budget, array $audit, int $preparationsCount): array

    {

        $checks = [

            'enveloppe_creee' => $budget !== null,

            'audit_sans_anomalie' => ($audit['anomalies_total'] ?? 1) === 0,

            'preparations_init' => $preparationsCount > 0,

            'pluri_rattache' => $budget && $budget->programmation_pluriannuelle_id !== null,

            'consolidation_ok' => $budget && BudgetConsolidation::where('budget_id', $budget->id)->exists(),

        ];



        $ok = count(array_filter($checks));

        $total = count($checks);



        return [

            'score_pct' => $total > 0 ? (int) round(($ok / $total) * 100) : 0,

            'checks' => $checks,

        ];

    }



    /** Sections normalisées pour RapportComposer. */

    /** @return array<int,array<string,mixed>> */

    public function sectionsRapport(Exercice $exercice): array

    {

        $s = $this->synthese($exercice->id);

        $env = $s['enveloppe'] ?? null;

        $m = $s['montants'] ?? [];

        $conf = $s['conformite'] ?? [];



        return [

            [

                'type' => 'kpi',

                'titre' => 'Synthèse budgétaire',

                'items' => [

                    ['label' => 'Exercice', 'valeur' => $exercice->annee, 'fmt' => 'text'],

                    ['label' => 'Enveloppe', 'valeur' => $env['reference'] ?? '—', 'fmt' => 'text'],

                    ['label' => 'Statut enveloppe', 'valeur' => $env['statut'] ?? '—', 'fmt' => 'text'],

                    ['label' => 'Crédits ouverts', 'valeur' => $m['ouvert'] ?? 0, 'fmt' => 'money'],

                    ['label' => 'Crédits révisés', 'valeur' => $m['revise'] ?? 0, 'fmt' => 'money'],

                    ['label' => 'Engagé', 'valeur' => $m['engage'] ?? 0, 'fmt' => 'money'],

                    ['label' => 'Payé', 'valeur' => $m['paye'] ?? 0, 'fmt' => 'money'],

                    ['label' => 'Conformité cycle', 'valeur' => ($conf['score_pct'] ?? 0).'%', 'fmt' => 'text'],

                    ['label' => 'Anomalies audit', 'valeur' => $s['audit']['anomalies_total'] ?? 0, 'fmt' => 'int'],

                ],

            ],

            [

                'type' => 'table',

                'titre' => 'Cycle institutionnel (B0–B4)',

                'colonnes' => [

                    ['label' => 'Phase', 'fmt' => 'text', 'align' => 'left'],

                    ['label' => 'Indicateur', 'fmt' => 'text', 'align' => 'left'],

                    ['label' => 'Valeur', 'fmt' => 'text', 'align' => 'right'],

                ],

                'lignes' => [

                    ['B0 Données', 'Anomalies audit', $s['audit']['anomalies_total'] ?? 0],

                    ['B1 Enveloppe', 'Statut', $env['statut'] ?? 'absente'],

                    ['B2 Préparation', 'Propositions', $s['preparation']['total'] ?? 0],

                    ['B3 Consolidation', 'Snapshots', $s['consolidations']['count'] ?? 0],

                    ['B3 Simulation', 'Scénarios', $s['simulations']['count'] ?? 0],

                    ['B4 Révisions', 'Actes validés', $s['revisions']['count'] ?? 0],

                    ['B4 Pluriannuel', 'Rattaché', $s['pluriannuel'] ? 'oui' : 'non'],

                ],

            ],

        ];

    }

}

