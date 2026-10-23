<?php

namespace App\Services\Reporting;

use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\EvaluationPeriodique;
use App\Models\Exercice;
use App\Services\Pap\PapPerformanceService;
use App\Services\Rbm\IndicateurPerformanceService;
use App\Services\Suivi\SuiviEvaluationService;
use InvalidArgumentException;

/** Composition multi-volets des rapports institutionnels périodiques (P1). */
class RapportPeriodiqueService
{
    public function __construct(
        private readonly ReportingService $reporting,
        private readonly RapportPeriodeService $periodes,
        private readonly PapPerformanceService $pap,
        private readonly IndicateurPerformanceService $iov,
        private readonly SuiviEvaluationService $suiviEval,
        private readonly ActivitesRetardService $retards,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function sectionsTrimestriel(Exercice $exercice, array $params): array
    {
        $ctx = $this->ctx($exercice, $params, 'trimestriel');

        return array_merge(
            [$this->intro($ctx, 'trimestriel')],
            $this->voletsCommuns($exercice, $ctx),
            [$this->campagnesPeriode($exercice, $ctx)],
            [$this->evaluationsPeriode($exercice, $ctx)],
            [$this->realisationsMajeures($exercice)],
            [$this->difficultes($exercice)],
            [$this->recommandationsInstitutionnelles($exercice)],
        );
    }

    /** @return list<array<string, mixed>> */
    public function sectionsSemestriel(Exercice $exercice, array $params): array
    {
        $ctx = $this->ctx($exercice, $params, 'semestriel');

        return array_merge(
            [$this->intro($ctx, 'semestriel')],
            $this->voletsCommuns($exercice, $ctx),
            [$this->comparatifSemestres($exercice, $ctx)],
            [$this->comparatifNn1($exercice)],
            [$this->realisationsMajeures($exercice, 8)],
            [$this->difficultes($exercice, 8)],
            [$this->recommandationsInstitutionnelles($exercice)],
        );
    }

    /** @return list<array<string, mixed>> */
    public function sectionsAnnuel(Exercice $exercice, array $params): array
    {
        $ctx = $this->ctx($exercice, $params, 'annuel');
        $se = $this->suiviEval->rapportDonnees($exercice->id);
        $pap = $this->pap->executionPap($exercice->id);
        $global = $this->reporting->global($exercice->id);
        $feux = $this->iov->feuxGlobaux($exercice->id);
        $reco = $this->reporting->recommandations($exercice->id);
        $formations = $this->reporting->formations($exercice->id);
        $detailReco = $reco['detail'];

        return [
            $this->intro($ctx, 'annuel'),
            [
                'type' => 'kpi',
                'titre' => 'Synthèse institutionnelle annuelle',
                'items' => [
                    ['label' => 'Score global S&E', 'valeur' => $se['scores_courants']['score_global'], 'fmt' => 'percent'],
                    ['label' => 'Niveau S&E', 'valeur' => EvaluationPeriodique::NIVEAUX[$se['niveau']] ?? $se['niveau'], 'fmt' => 'text'],
                    ['label' => "Taux d'exécution budgétaire", 'valeur' => $global['taux_execution'], 'fmt' => 'percent'],
                    ['label' => 'Taux physique PAP', 'valeur' => $pap['taux_physique'], 'fmt' => 'percent'],
                    ['label' => 'Taux financier PAP', 'valeur' => $pap['taux_financier'], 'fmt' => 'percent'],
                    ['label' => 'Indicateurs IOV au vert', 'valeur' => $feux['vert'], 'fmt' => 'int'],
                    ['label' => 'Indicateurs IOV au rouge', 'valeur' => $feux['rouge'], 'fmt' => 'int'],
                    ['label' => 'Recommandations suivies', 'valeur' => $detailReco->where('statut', 'Réalisée')->count(), 'fmt' => 'int'],
                    ['label' => 'Sessions formation', 'valeur' => $formations['detail']->count(), 'fmt' => 'int'],
                ],
            ],
            $this->voletStrategique($exercice),
            $this->voletBudgetaire($global),
            $this->voletFinancier($global),
            [
                'type' => 'kpi',
                'titre' => 'Chaîne S&E — missions et plans',
                'items' => [
                    ['label' => 'Missions en cours', 'valeur' => $se['missions']['en_cours'] ?? 0, 'fmt' => 'int'],
                    ['label' => 'Plans en cours', 'valeur' => $se['plans']['en_cours'] ?? 0, 'fmt' => 'int'],
                    ['label' => 'Taux moyen plans', 'valeur' => $se['plans']['taux_moyen'] ?? 0, 'fmt' => 'percent'],
                ],
            ],
            $this->indicateursCles($exercice),
            $this->historiqueEvaluations($exercice),
            $this->realisationsMajeures($exercice, 10),
            $this->difficultes($exercice, 10),
            $this->recommandationsInstitutionnelles($exercice),
        ];
    }

    /** @return array{periode:string, periode_type:string, periode_debut:string, periode_fin:string, libelle:string} */
    private function ctx(Exercice $exercice, array $params, string $typeAttendu): array
    {
        $saisie = $params['periode'] ?? match ($typeAttendu) {
            'trimestriel' => $this->periodes->trimestreCourant($exercice->annee),
            'semestriel' => $this->periodes->semestreCourant($exercice->annee),
            default => (string) $exercice->annee,
        };

        $ctx = $this->periodes->resoudre($exercice, $saisie);

        if ($typeAttendu === 'trimestriel' && $ctx['periode_type'] !== 'trimestriel') {
            $ctx = $this->periodes->resoudre($exercice, $this->periodes->trimestreCourant($exercice->annee));
        }
        if ($typeAttendu === 'semestriel' && $ctx['periode_type'] !== 'semestriel') {
            $ctx = $this->periodes->resoudre($exercice, $this->periodes->semestreCourant($exercice->annee));
        }
        if ($typeAttendu === 'annuel' && $ctx['periode_type'] !== 'annuel') {
            $ctx = $this->periodes->resoudre($exercice, (string) $exercice->annee);
        }

        $ctx['libelle'] = $this->periodes->libelle($ctx['periode']);

        return $ctx;
    }

    /** @param array{periode:string, periode_debut:string, periode_fin:string, libelle:string} $ctx */
    private function intro(array $ctx, string $nature): array
    {
        $texte = match ($nature) {
            'trimestriel' => "Rapport trimestriel institutionnel couvrant la période du {$ctx['periode_debut']} au {$ctx['periode_fin']}. "
                .'Consolidation des volets stratégique, physique, financier et budgétaire.',
            'semestriel' => "Rapport semestriel institutionnel — {$ctx['libelle']}. "
                .'Analyse comparative intra-exercice et tendances de performance.',
            default => "Rapport annuel consolidé de performance et de mise en œuvre — exercice {$ctx['periode']}. "
                .'Synthèse PAP, exécution budgétaire, indicateurs IOV et suivi-évaluation.',
        };

        return ['type' => 'text', 'titre' => 'Contexte', 'contenu' => $texte];
    }

    /** @return list<array<string, mixed>> */
    private function voletsCommuns(Exercice $exercice, array $ctx): array
    {
        $global = $this->reporting->global($exercice->id);
        $pap = $this->pap->executionPap($exercice->id);
        $se = $this->suiviEval->rapportDonnees($exercice->id);

        return [
            [
                'type' => 'kpi',
                'titre' => 'Indicateurs clés de la période',
                'items' => [
                    ['label' => 'Période', 'valeur' => $ctx['libelle'], 'fmt' => 'text'],
                    ['label' => 'Score global S&E', 'valeur' => $se['scores_courants']['score_global'], 'fmt' => 'percent'],
                    ['label' => "Taux d'exécution budgétaire", 'valeur' => $global['taux_execution'], 'fmt' => 'percent'],
                    ['label' => 'Taux physique PAP', 'valeur' => $pap['taux_physique'], 'fmt' => 'percent'],
                    ['label' => 'Taux financier PAP', 'valeur' => $pap['taux_financier'], 'fmt' => 'percent'],
                ],
            ],
            $this->voletStrategique($exercice),
            $this->voletBudgetaire($global),
            $this->voletFinancier($global),
            $this->indicateursCles($exercice),
        ];
    }

    private function voletStrategique(Exercice $exercice): array
    {
        $axes = $this->reporting->parAxe($exercice->id)->sortByDesc('taux_execution')->take(8);

        return [
            'type' => 'table',
            'titre' => 'Volet stratégique — exécution par axe',
            'colonnes' => [
                ['label' => 'Axe', 'fmt' => 'text', 'align' => 'left'],
                ['label' => 'Alloué', 'fmt' => 'money', 'align' => 'right'],
                ['label' => 'Payé', 'fmt' => 'money', 'align' => 'right'],
                ['label' => "Taux exéc.", 'fmt' => 'percent', 'align' => 'right'],
            ],
            'lignes' => $axes->map(fn ($r) => [
                $r['libelle'] ?? $r['intitule'] ?? $r['code'] ?? '—', $r['alloue'] ?? $r['ouvert'], $r['paye'], $r['taux_execution'] ?? 0,
            ])->all(),
        ];
    }

    /** @param array<string, float|int> $global */
    private function voletBudgetaire(array $global): array
    {
        return [
            'type' => 'kpi',
            'titre' => 'Volet budgétaire',
            'items' => [
                ['label' => 'Crédits alloués', 'valeur' => $global['alloue'] ?? $global['ouvert'], 'fmt' => 'money'],
                ['label' => 'Engagés', 'valeur' => $global['engage'], 'fmt' => 'money'],
                ['label' => 'Payés', 'valeur' => $global['paye'], 'fmt' => 'money'],
                ['label' => "Taux d'engagement", 'valeur' => $global['taux_engagement'], 'fmt' => 'percent'],
                ['label' => "Taux d'exécution", 'valeur' => $global['taux_execution'], 'fmt' => 'percent'],
            ],
        ];
    }

    /** @param array<string, float|int> $global */
    private function voletFinancier(array $global): array
    {
        return [
            'type' => 'kpi',
            'titre' => 'Volet financier — restes et disponibilités',
            'items' => [
                ['label' => 'Crédits disponibles', 'valeur' => $global['disponible'], 'fmt' => 'money'],
                ['label' => 'Restes à engager', 'valeur' => $global['reste_a_engager'], 'fmt' => 'money'],
                ['label' => 'Restes à payer', 'valeur' => $global['reste_a_payer'], 'fmt' => 'money'],
            ],
        ];
    }

    private function indicateursCles(Exercice $exercice): array
    {
        $feux = $this->iov->feuxGlobaux($exercice->id);
        $rows = $this->iov->evolutionExercice($exercice->id)
            ->filter(fn ($r) => in_array($r['feu'] ?? '', ['orange', 'rouge'], true))
            ->take(10);

        return [
            'type' => 'table',
            'titre' => 'Indicateurs IOV — points de vigilance',
            'colonnes' => [
                ['label' => 'Code', 'fmt' => 'text', 'align' => 'left'],
                ['label' => 'Libellé', 'fmt' => 'text', 'align' => 'left'],
                ['label' => 'Feu', 'fmt' => 'text', 'align' => 'left'],
                ['label' => 'Taux', 'fmt' => 'percent', 'align' => 'right'],
            ],
            'lignes' => $rows->map(fn ($r) => [
                $r['code'] ?? '—', $r['libelle'] ?? '—', $r['feu'] ?? '—', $r['taux_atteinte'] ?? 0,
            ])->all() ?: [['—', 'Aucun indicateur orange/rouge', 'vert', 0]],
        ];
    }

    /** @param array{periode:string, periode_debut:string, periode_fin:string} $ctx */
    private function campagnesPeriode(Exercice $exercice, array $ctx): array
    {
        $campagnes = CampagneSuivi::where('exercice_id', $exercice->id)
            ->where(function ($q) use ($ctx) {
                $q->where('periode', $ctx['periode'])
                    ->orWhereBetween('date_debut', [$ctx['periode_debut'], $ctx['periode_fin']]);
            })
            ->get();

        $collectes = Collecte::whereIn('campagne_id', $campagnes->pluck('id'))->count();
        $validees = Collecte::whereIn('campagne_id', $campagnes->pluck('id'))->where('statut', 'validee')->count();

        return [
            'type' => 'kpi',
            'titre' => 'Volet physique — campagnes de suivi',
            'items' => [
                ['label' => 'Campagnes sur la période', 'valeur' => $campagnes->count(), 'fmt' => 'int'],
                ['label' => 'Collectes saisies', 'valeur' => $collectes, 'fmt' => 'int'],
                ['label' => 'Collectes validées', 'valeur' => $validees, 'fmt' => 'int'],
                ['label' => 'Campagnes clôturées', 'valeur' => $campagnes->where('statut', 'cloturee')->count(), 'fmt' => 'int'],
            ],
        ];
    }

    /** @param array{periode:string, periode_debut:string, periode_fin:string} $ctx */
    private function evaluationsPeriode(Exercice $exercice, array $ctx): array
    {
        $evals = EvaluationPeriodique::where('exercice_id', $exercice->id)
            ->where(function ($q) use ($ctx) {
                $q->where('periode', $ctx['periode'])
                    ->orWhereBetween('genere_le', [$ctx['periode_debut'].' 00:00:00', $ctx['periode_fin'].' 23:59:59']);
            })
            ->orderByDesc('genere_le')
            ->get();

        return [
            'type' => 'table',
            'titre' => 'Évaluations périodiques S&E sur la période',
            'colonnes' => [
                ['label' => 'Période', 'fmt' => 'text', 'align' => 'left'],
                ['label' => 'Type', 'fmt' => 'text', 'align' => 'left'],
                ['label' => 'Score', 'fmt' => 'percent', 'align' => 'right'],
                ['label' => 'Niveau', 'fmt' => 'text', 'align' => 'left'],
            ],
            'lignes' => $evals->map(fn ($e) => [
                $e->periode,
                $e->type,
                (float) $e->score_global,
                EvaluationPeriodique::NIVEAUX[$e->niveau] ?? $e->niveau,
            ])->all() ?: [['—', '—', 0, 'Aucune évaluation sur la période']],
        ];
    }

    private function comparatifSemestres(Exercice $exercice, array $ctx): array
    {
        $s1 = $this->periodes->resoudre($exercice, "{$exercice->annee}-S1");
        $s2 = $this->periodes->resoudre($exercice, "{$exercice->annee}-S2");

        $campagnes = fn (array $p) => CampagneSuivi::where('exercice_id', $exercice->id)
            ->where('periode', $p['periode'])->count();
        $evals = fn (array $p) => EvaluationPeriodique::where('exercice_id', $exercice->id)
            ->where('periode', $p['periode'])->count();

        $global = $this->reporting->global($exercice->id);

        return [
            'type' => 'table',
            'titre' => 'Analyse comparative S1 / S2',
            'colonnes' => [
                ['label' => 'Indicateur', 'fmt' => 'text', 'align' => 'left'],
                ['label' => 'S1', 'fmt' => 'text', 'align' => 'right'],
                ['label' => 'S2', 'fmt' => 'text', 'align' => 'right'],
                ['label' => 'Écart', 'fmt' => 'text', 'align' => 'right'],
            ],
            'lignes' => [
                ['Campagnes de suivi', $campagnes($s1), $campagnes($s2), $campagnes($s2) - $campagnes($s1)],
                ['Évaluations S&E', $evals($s1), $evals($s2), $evals($s2) - $evals($s1)],
                [
                    "Taux exécution (cumul annuel)",
                    $global['taux_execution'].' %',
                    $global['taux_execution'].' %',
                    '—',
                ],
            ],
        ];
    }

    private function comparatifNn1(Exercice $exercice): array
    {
        $precedent = Exercice::where('annee', $exercice->annee - 1)->first();
        if (! $precedent) {
            return [
                'type' => 'text',
                'titre' => 'Comparaison N / N-1',
                'contenu' => 'Exercice N-1 non disponible — comparaison inter-annuelle non applicable.',
            ];
        }

        $n = $this->reporting->global($exercice->id);
        $n1 = $this->reporting->global($precedent->id);
        $papN = $this->pap->executionPap($exercice->id);
        $papN1 = $this->pap->executionPap($precedent->id);

        return [
            'type' => 'table',
            'titre' => 'Comparaison N / N-1',
            'colonnes' => [
                ['label' => 'Indicateur', 'fmt' => 'text', 'align' => 'left'],
                ['label' => (string) ($exercice->annee - 1), 'fmt' => 'text', 'align' => 'right'],
                ['label' => (string) $exercice->annee, 'fmt' => 'text', 'align' => 'right'],
                ['label' => 'Écart (pts)', 'fmt' => 'num', 'align' => 'right'],
            ],
            'lignes' => [
                ["Taux d'exécution", $n1['taux_execution'], $n['taux_execution'], round($n['taux_execution'] - $n1['taux_execution'], 1)],
                ['Taux physique PAP', $papN1['taux_physique'], $papN['taux_physique'], round($papN['taux_physique'] - $papN1['taux_physique'], 1)],
                ['Montant payé', $n1['paye'], $n['paye'], round($n['paye'] - $n1['paye'], 0)],
            ],
        ];
    }

    private function historiqueEvaluations(Exercice $exercice): array
    {
        $rows = EvaluationPeriodique::where('exercice_id', $exercice->id)
            ->orderByDesc('genere_le')
            ->limit(12)
            ->get();

        return [
            'type' => 'table',
            'titre' => 'Historique des évaluations périodiques',
            'colonnes' => [
                ['label' => 'Période', 'fmt' => 'text', 'align' => 'left'],
                ['label' => 'Score global', 'fmt' => 'percent', 'align' => 'right'],
                ['label' => 'Niveau', 'fmt' => 'text', 'align' => 'left'],
                ['label' => 'Généré le', 'fmt' => 'text', 'align' => 'left'],
            ],
            'lignes' => $rows->map(fn ($e) => [
                $e->periode,
                (float) $e->score_global,
                EvaluationPeriodique::NIVEAUX[$e->niveau] ?? $e->niveau,
                $e->genere_le?->format('d/m/Y') ?? '—',
            ])->all() ?: [['—', 0, '—', '—']],
        ];
    }

    private function realisationsMajeures(Exercice $exercice, int $limit = 5): array
    {
        $activites = $this->pap->performanceActivites($exercice->id)
            ->sortByDesc('taux_physique')
            ->take($limit);

        return [
            'type' => 'liste',
            'titre' => 'Réalisations majeures',
            'items' => $activites->map(fn ($a) => sprintf(
                '%s — taux physique %s %%, financier %s %%',
                $a['intitule'] ?? $a['code'] ?? 'Activité',
                $a['taux_physique'] ?? 0,
                $a['taux_financier'] ?? 0,
            ))->all() ?: ['Aucune activité avec réalisation significative enregistrée.'],
        ];
    }

    private function difficultes(Exercice $exercice, int $limit = 5): array
    {
        $retard = $this->retards->collection($exercice->id)->take($limit);

        return [
            'type' => 'liste',
            'titre' => 'Difficultés et retards identifiés',
            'items' => $retard->map(fn ($a) => sprintf(
                '%s (%s) — retard %d j · taux phys. %s %%',
                $a->code,
                $a->intitule,
                $this->retards->joursRetard($a),
                $a->taux_realisation_physique,
            ))->all() ?: ['Aucune activité en retard critique sur la période.'],
        ];
    }

    private function recommandationsInstitutionnelles(Exercice $exercice): array
    {
        $detail = $this->reporting->recommandations($exercice->id)['detail'];

        return [
            'type' => 'kpi',
            'titre' => 'Recommandations institutionnelles',
            'items' => [
                ['label' => 'Total actives', 'valeur' => $detail->count(), 'fmt' => 'int'],
                ['label' => 'Réalisées', 'valeur' => $detail->where('statut', 'Réalisée')->count(), 'fmt' => 'int'],
                ['label' => 'Taux moyen exécution', 'valeur' => round((float) $detail->avg('taux'), 1), 'fmt' => 'percent'],
                ['label' => 'En cours', 'valeur' => $detail->where('statut', 'En cours')->count(), 'fmt' => 'int'],
            ],
        ];
    }
}
