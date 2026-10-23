<?php

namespace App\Services\Reporting;

use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use App\Models\RapportPersonnalise;
use App\Services\Patrimoine\ImmobilisationService;
use Illuminate\Support\Collection;

/** Sources de données et composition des rapports personnalisés (P3-1). */
class RapportPersonnaliseService
{
    /** @var array<string, array{label: string, champs: array<string, array{label: string, fmt: string}>}> */
    public const SOURCES = [
        'lignes_budgetaires' => [
            'label' => 'Lignes budgétaires',
            'champs' => [
                'code' => ['label' => 'Code', 'fmt' => 'text'],
                'intitule' => ['label' => 'Intitulé', 'fmt' => 'text'],
                'structure' => ['label' => 'Structure', 'fmt' => 'text'],
                'source' => ['label' => 'Source financement', 'fmt' => 'text'],
                'ouvert' => ['label' => 'Crédit ouvert', 'fmt' => 'money'],
                'engage' => ['label' => 'Engagé', 'fmt' => 'money'],
                'liquide' => ['label' => 'Liquidé', 'fmt' => 'money'],
                'ordonnance' => ['label' => 'Ordonnancé', 'fmt' => 'money'],
                'paye' => ['label' => 'Payé', 'fmt' => 'money'],
                'taux_paye' => ['label' => 'Taux exécution', 'fmt' => 'percent'],
            ],
        ],
        'engagements' => [
            'label' => 'Engagements',
            'champs' => [
                'numero' => ['label' => 'N°', 'fmt' => 'text'],
                'objet' => ['label' => 'Objet', 'fmt' => 'text'],
                'structure' => ['label' => 'Structure', 'fmt' => 'text'],
                'montant' => ['label' => 'Montant', 'fmt' => 'money'],
                'statut' => ['label' => 'Statut', 'fmt' => 'text'],
                'date' => ['label' => 'Date', 'fmt' => 'text'],
            ],
        ],
        'formations' => [
            'label' => 'Formations & ateliers',
            'champs' => [
                'numero' => ['label' => 'N°', 'fmt' => 'text'],
                'intitule' => ['label' => 'Intitulé', 'fmt' => 'text'],
                'type' => ['label' => 'Type', 'fmt' => 'text'],
                'participants' => ['label' => 'Participants', 'fmt' => 'int'],
                'hommes' => ['label' => 'Hommes', 'fmt' => 'int'],
                'femmes' => ['label' => 'Femmes', 'fmt' => 'int'],
                'cout' => ['label' => 'Coût', 'fmt' => 'money'],
                'satisfaction' => ['label' => 'Satisfaction', 'fmt' => 'percent'],
                'statut' => ['label' => 'Statut', 'fmt' => 'text'],
            ],
        ],
        'immobilisations' => [
            'label' => 'Immobilisations',
            'champs' => [
                'numero' => ['label' => 'N°', 'fmt' => 'text'],
                'designation' => ['label' => 'Désignation', 'fmt' => 'text'],
                'categorie' => ['label' => 'Catégorie', 'fmt' => 'text'],
                'structure' => ['label' => 'Structure', 'fmt' => 'text'],
                'valeur_acquisition' => ['label' => 'Valeur acquisition', 'fmt' => 'money'],
                'amortissement_cumule' => ['label' => 'Amortissement', 'fmt' => 'money'],
                'valeur_nette' => ['label' => 'VNC', 'fmt' => 'money'],
                'statut' => ['label' => 'Statut', 'fmt' => 'text'],
            ],
        ],
        'recommandations' => [
            'label' => 'Recommandations',
            'champs' => [
                'numero' => ['label' => 'N°', 'fmt' => 'text'],
                'source' => ['label' => 'Source', 'fmt' => 'text'],
                'libelle' => ['label' => 'Libellé', 'fmt' => 'text'],
                'structure' => ['label' => 'Structure', 'fmt' => 'text'],
                'priorite' => ['label' => 'Priorité', 'fmt' => 'text'],
                'echeance' => ['label' => 'Échéance', 'fmt' => 'text'],
                'statut' => ['label' => 'Statut', 'fmt' => 'text'],
                'taux' => ['label' => 'Taux exécution', 'fmt' => 'percent'],
            ],
        ],
    ];

    public function __construct(
        private readonly ReportingService $reporting,
        private readonly ImmobilisationService $immobilisations,
    ) {
    }

    /** @return array{type: string, titre: string, sous_titre: string, sections: array} */
    public function composer(RapportPersonnalise $modele, int $exerciceId, array $params = []): array
    {
        $colonnes = collect($modele->colonnes)->filter(fn ($c) => isset(self::SOURCES[$modele->source]['champs'][$c]));
        abort_if($colonnes->isEmpty(), 422, 'Aucune colonne valide sélectionnée.');

        $lignes = $this->donnees($modele, $exerciceId, $params);
        $champs = self::SOURCES[$modele->source]['champs'];
        $cols = $colonnes->map(fn ($c) => ['label' => $champs[$c]['label'], 'fmt' => $champs[$c]['fmt'], 'align' => null])->values()->all();
        $rows = $lignes->map(fn ($r) => $colonnes->map(fn ($c) => $r[$c] ?? '')->all())->all();

        $total = null;
        if ($lignes->isNotEmpty()) {
            $premiereCol = $colonnes->first();
            $total = $colonnes->map(function ($c) use ($champs, $lignes, $premiereCol) {
                if (($champs[$c]['fmt'] ?? 'text') === 'money') {
                    return $lignes->sum($c);
                }
                if (($champs[$c]['fmt'] ?? 'text') === 'int') {
                    return $lignes->sum($c);
                }

                return $c === $premiereCol ? 'TOTAL' : '';
            })->all();
        }

        return [
            'type' => 'personnalise',
            'titre' => $modele->titre,
            'sous_titre' => (self::SOURCES[$modele->source]['label'] ?? $modele->source).' · Exercice '.($params['exercice_annee'] ?? ''),
            'sections' => [
                [
                    'type' => 'kpi',
                    'titre' => 'Synthèse',
                    'items' => [
                        ['label' => 'Source', 'valeur' => self::SOURCES[$modele->source]['label'], 'fmt' => 'text'],
                        ['label' => 'Lignes', 'valeur' => $lignes->count(), 'fmt' => 'int'],
                        ['label' => 'Colonnes', 'valeur' => $colonnes->count(), 'fmt' => 'int'],
                    ],
                ],
                [
                    'type' => 'table',
                    'titre' => $modele->titre,
                    'colonnes' => $cols,
                    'lignes' => $rows,
                    'total' => $total,
                ],
            ],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function donnees(RapportPersonnalise $modele, int $exerciceId, array $params): Collection
    {
        $filtres = array_merge($modele->filtres ?? [], array_filter([
            'structure_id' => $params['structure_id'] ?? null,
            'statut' => $params['statut'] ?? null,
        ]));

        return match ($modele->source) {
            'lignes_budgetaires' => $this->lignesBudgetaires($exerciceId, $filtres),
            'engagements' => $this->engagements($exerciceId, $filtres),
            'formations' => collect($this->reporting->formations($exerciceId)['detail']),
            'immobilisations' => collect($this->immobilisations->syntheseExercice($exerciceId)['detail']),
            'recommandations' => collect($this->reporting->recommandations($exerciceId)['detail']),
            default => collect(),
        };
    }

    /** @param  array<string, mixed>  $filtres */
    private function lignesBudgetaires(int $exerciceId, array $filtres): Collection
    {
        return LigneBudgetaire::with(['structure', 'sourceFinancement', 'rubrique'])
            ->where('exercice_id', $exerciceId)
            ->when($filtres['structure_id'] ?? null, fn ($q, $id) => $q->where('structure_id', $id))
            ->orderBy('code')
            ->get()
            ->map(fn (LigneBudgetaire $l) => [
                'code' => $l->code ?? $l->rubrique?->code ?? '—',
                'intitule' => $l->intitule ?? $l->rubrique?->libelle ?? '—',
                'structure' => $l->structure?->sigle ?? $l->structure?->libelle ?? '—',
                'source' => $l->sourceFinancement?->libelle ?? '—',
                'ouvert' => (float) $l->montant_ouvert,
                'engage' => (float) $l->montant_engage,
                'liquide' => (float) $l->montant_liquide,
                'ordonnance' => (float) $l->montant_ordonnance,
                'paye' => (float) $l->montant_paye,
                'taux_paye' => $l->montant_ouvert > 0 ? round($l->montant_paye / $l->montant_ouvert * 100, 1) : 0.0,
            ]);
    }

    /** @param  array<string, mixed>  $filtres */
    private function engagements(int $exerciceId, array $filtres): Collection
    {
        return Engagement::with('expressionBesoin.structure')
            ->where('exercice_id', $exerciceId)
            ->when($filtres['structure_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'expressionBesoin',
                fn ($q2) => $q2->where('structure_id', $id)
            ))
            ->when($filtres['statut'] ?? null, fn ($q, $s) => $q->where('statut', $s))
            ->orderBy('numero')
            ->get()
            ->map(fn (Engagement $e) => [
                'numero' => $e->numero,
                'objet' => $e->objet,
                'structure' => $e->expressionBesoin?->structure?->sigle
                    ?? $e->expressionBesoin?->structure?->libelle ?? '—',
                'montant' => (float) $e->montant_total,
                'statut' => $e->statut,
                'date' => $e->date_engagement?->format('d/m/Y') ?? '—',
            ]);
    }
}
