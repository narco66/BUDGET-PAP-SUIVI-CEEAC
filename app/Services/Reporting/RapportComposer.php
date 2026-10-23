<?php

namespace App\Services\Reporting;

use App\Models\EvaluationPeriodique;
use App\Models\Exercice;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Services\Budget\SuiviFinancierCalculator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Compose un rapport sous forme de SECTIONS normalisées, source de données UNIQUE consommée à
 * l'identique par les rendus PDF, Excel et Word. C'est ce qui garantit que les montants sont
 * strictement identiques dans tous les formats (aucun recalcul propre à un format).
 *
 * Structure d'une section :
 *  - ['type'=>'kpi',   'titre'=>…, 'items'=>[['label'=>…, 'valeur'=>…, 'fmt'=>'money|percent|int|text']]]
 *  - ['type'=>'table', 'titre'=>…, 'colonnes'=>[['label'=>…, 'fmt'=>…, 'align'=>…]], 'lignes'=>[[…]], 'total'=>[…]|null, 'niveaux'=>[…]?]
 *  - ['type'=>'text',  'titre'=>…, 'contenu'=>…]
 *  - ['type'=>'liste', 'titre'=>…, 'items'=>[…]]
 */
class RapportComposer
{
    /** Catalogue des rapports disponibles (alimentés par des données réelles). */
    public const TYPES = [
        'pap' => 'Rapport de performance du PAP',
        'execution_synthese' => 'Synthèse d\'exécution budgétaire',
        'execution_axe' => 'Exécution budgétaire par axe',
        'bailleur' => 'Exécution par bailleur (CEEAC / PTF)',
        'categorie' => 'Exécution budgétaire par catégorie',
        'rbm_gar' => 'Rapport technico-financier RBM/GAR',
        'marches' => 'Rapport d’exécution du PPM (marchés)',
        'contrats' => 'Registre des contrats',
        'recommandations' => 'Rapport de suivi des recommandations',
        'formations' => 'Rapport des ateliers et formations',
        'immobilisations' => 'Registre des immobilisations & inventaire',
        'nomenclature' => 'Nomenclature budgétaire',
        'budget_institutionnel' => 'Rapport budgétaire institutionnel (cycle B0–B4)',
        'situation_budgetaire' => 'Situation budgétaire consolidée',
        'indicateurs_evolution' => 'Évolution et écarts des indicateurs IOV',
        'ecarts_physique_financier' => 'Écarts physique / financier (PAP)',
        'suivi_evaluation' => 'Rapport de suivi-évaluation institutionnel',
        'trimestriel' => 'Rapport trimestriel institutionnel',
        'semestriel' => 'Rapport semestriel institutionnel',
        'annuel' => 'Rapport annuel institutionnel consolidé',
        'performance_pilier' => 'Performance par pilier stratégique',
        'performance_axe' => 'Performance par axe stratégique',
        'performance_departement' => 'Performance par département',
        'recettes_mobilisation' => 'Mobilisation des contributions (États membres)',
        'recettes_financier' => 'Rapport financier des recettes',
        'tresorerie_flux' => 'Rapport de flux de trésorerie',
        'tresorerie_dette_fournisseurs' => 'Dette fournisseurs et restes à payer',
        'ipsas_etat_financier' => 'État financier IPSAS — bilan, résultat et annexes',
        'technique_sante' => 'Rapport technique — Santé plateforme',
        'technique_integrite' => 'Rapport technique — Intégrité des données',
        'technique_audit' => 'Rapport technique — Journaux d\'audit',
    ];

    public function __construct(
        private readonly ReportingService $svc,
        private readonly RapportPeriodiqueService $periodique,
        private readonly RapportStrategiqueService $strategique,
        private readonly RapportIpsasService $ipsas,
        private readonly RapportPersonnaliseService $personnaliseSvc,
        private readonly RapportTechniqueService $techniqueSvc,
    ) {
    }

    public function titre(string $type): string
    {
        return self::TYPES[$type] ?? throw new InvalidArgumentException("Type de rapport inconnu : {$type}");
    }

    /** @return array{type:string,titre:string,sous_titre:string,sections:array} */
    public function composer(string $type, Exercice $exercice, array $params = []): array
    {
        if ($type === 'personnalise') {
            return $this->personnalise($exercice, $params);
        }

        $sections = match ($type) {
            'pap' => $this->pap($exercice, $params),
            'execution_synthese' => $this->executionSynthese($exercice),
            'execution_axe' => $this->executionAxe($exercice),
            'bailleur' => $this->bailleur($exercice),
            'categorie' => $this->categorie($exercice),
            'rbm_gar' => $this->rbmGar($exercice),
            'marches' => $this->marches($exercice),
            'contrats' => $this->contrats($exercice),
            'recommandations' => $this->recommandations($exercice),
            'formations' => $this->formations($exercice),
            'immobilisations' => $this->immobilisations($exercice),
            'nomenclature' => $this->nomenclature(),
            'budget_institutionnel' => $this->budgetInstitutionnel($exercice),
            'situation_budgetaire' => $this->situationBudgetaire($exercice),
            'indicateurs_evolution' => $this->indicateursEvolution($exercice),
            'ecarts_physique_financier' => $this->ecartsPhysiqueFinancier($exercice),
            'suivi_evaluation' => $this->suiviEvaluation($exercice),
            'trimestriel' => $this->periodique->sectionsTrimestriel($exercice, $params),
            'semestriel' => $this->periodique->sectionsSemestriel($exercice, $params),
            'annuel' => $this->periodique->sectionsAnnuel($exercice, $params),
            'performance_pilier' => $this->strategique->sectionsPerformancePilier($exercice),
            'performance_axe' => $this->strategique->sectionsPerformanceAxe($exercice),
            'performance_departement' => $this->strategique->sectionsPerformanceDepartement($exercice),
            'recettes_mobilisation' => $this->recettesMobilisation($exercice),
            'recettes_financier' => $this->recettesFinancier($exercice),
            'tresorerie_flux' => $this->tresorerieFlux($exercice),
            'tresorerie_dette_fournisseurs' => $this->tresorerieDetteFournisseurs($exercice),
            'ipsas_etat_financier' => $this->ipsas->sections($exercice),
            'technique_sante' => $this->techniqueSante(),
            'technique_integrite' => $this->techniqueIntegrite($exercice),
            'technique_audit' => $this->techniqueAudit(),
            default => throw new InvalidArgumentException("Type de rapport inconnu : {$type}"),
        };

        return [
            'type' => $type,
            'titre' => $this->titre($type),
            'sous_titre' => $this->sousTitre($exercice, $params),
            'sections' => $sections,
        ];
    }

    private function sousTitre(Exercice $exercice, array $params): string
    {
        $periode = $params['periode'] ?? (string) $exercice->annee;

        return 'Exercice '.$exercice->annee.' · Période '.$periode.' · Commission de la CEEAC';
    }

    /** Rapport construit par l'utilisateur (P3-1). */
    private function personnalise(Exercice $exercice, array $params): array
    {
        $modele = \App\Models\RapportPersonnalise::findOrFail($params['personnalise_id'] ?? $params['modele_id'] ?? 0);
        $composed = $this->personnaliseSvc->composer($modele, $exercice->id, [
            ...$params,
            'exercice_annee' => $exercice->annee,
        ]);
        $composed['sous_titre'] = $this->sousTitre($exercice, $params);

        return $composed;
    }

    // ---- Compositions par type --------------------------------------------------------------

    /** Synthèse globale + nature + structure (ex-rapports /rapports legacy). */
    private function executionSynthese(Exercice $ex): array
    {
        $global = $this->svc->global($ex->id);
        $natures = $this->svc->parCategorie($ex->id);
        $structures = $this->svc->parStructure($ex->id);

        return [
            $this->kpiGlobal($global),
            [
                'type' => 'table', 'titre' => 'Consommation par nature (titre)',
                'colonnes' => [
                    $this->col('Code'), $this->col('Nature'),
                    $this->col('Ouvert', 'money'), $this->col('Engagé', 'money'), $this->col('Payé', 'money'),
                ],
                'lignes' => $natures->map(fn ($r) => [$r['code'], $r['intitule'], $r['ouvert'], $r['engage'], $r['paye']])->all(),
            ],
            [
                'type' => 'table', 'titre' => 'Consommation par structure',
                'colonnes' => [
                    $this->col('Structure'), $this->col('Ouvert', 'money'),
                    $this->col('Engagé', 'money'), $this->col('Payé', 'money'),
                ],
                'lignes' => $structures->map(fn ($r) => [$r['libelle'], $r['ouvert'], $r['engage'], $r['paye']])->all(),
            ],
        ];
    }

    private function executionAxe(Exercice $ex): array
    {
        $rows = $this->svc->parAxe($ex->id);

        return [
            $this->kpiGlobal($this->svc->global($ex->id)),
            [
                'type' => 'table', 'titre' => 'Exécution par axe stratégique',
                'colonnes' => [
                    $this->col('Axe'), $this->col('Prévisions', 'money'), $this->col('Engagements', 'money'),
                    $this->col('Liquidations', 'money'), $this->col('Ordonnancements', 'money'),
                    $this->col('Paiements', 'money'), $this->col('Décaissements', 'money'),
                    $this->col('Taux exéc.', 'percent'),
                ],
                'lignes' => $rows->map(fn ($r) => [
                    trim(($r['code'] ?? '').' '.($r['intitule'] ?? '')),
                    $r['ouvert'], $r['engage'], $r['liquide'], $r['ordonnance'], $r['paye'], $r['decaisse'], $r['taux_execution'],
                ])->all(),
                'total' => [
                    'TOTAL GÉNÉRAL', $rows->sum('ouvert'), $rows->sum('engage'), $rows->sum('liquide'),
                    $rows->sum('ordonnance'), $rows->sum('paye'), $rows->sum('decaisse'), $this->tauxGlobal($rows),
                ],
            ],
        ];
    }

    private function bailleur(Exercice $ex): array
    {
        $data = $this->svc->parBailleur($ex->id);
        $multi = $this->svc->bailleurMultiAnnuel();

        return [
            [
                'type' => 'table', 'titre' => 'Comparaison CEEAC / PTF',
                'colonnes' => [$this->col('Origine'), $this->col('Prévisions', 'money'), $this->col('Engagements', 'money'), $this->col('Payé', 'money'), $this->col('Taux exéc.', 'percent')],
                'lignes' => $data['ceeac_ptf']->map(fn ($r) => [$r['type'], $r['ouvert'], $r['engage'], $r['paye'], $r['taux_execution']])->all(),
                'total' => null,
            ],
            [
                'type' => 'table', 'titre' => 'Détail par source de financement',
                'colonnes' => [$this->col('Source'), $this->col('Bailleur'), $this->col('Prévisions', 'money'), $this->col('Engagements', 'money'), $this->col('Payé', 'money'), $this->col('Taux exéc.', 'percent')],
                'lignes' => $data['sources']->map(fn ($r) => [$r['libelle'], $r['bailleur'] ?? '—', $r['ouvert'], $r['engage'], $r['paye'], $r['taux_execution']])->all(),
                'total' => null,
            ],
            [
                'type' => 'table', 'titre' => 'Évolution multi-annuelle des décaissements',
                'colonnes' => [$this->col('Exercice'), $this->col('CEEAC payé', 'money'), $this->col('PTF payé', 'money'), $this->col('Total payé', 'money')],
                'lignes' => $multi->map(fn ($r) => [(string) $r['annee'], $r['ceeac'], $r['ptf'], $r['paye']])->all(),
                'total' => null,
            ],
        ];
    }

    private function categorie(Exercice $ex): array
    {
        $rows = $this->svc->parCategorie($ex->id);

        return [
            $this->kpiGlobal($this->svc->global($ex->id)),
            [
                'type' => 'table', 'titre' => 'Exécution par catégorie (nature budgétaire)',
                'colonnes' => [
                    $this->col('Catégorie'), $this->col('Prévisions', 'money'), $this->col('Engagements', 'money'),
                    $this->col('Liquidations', 'money'), $this->col('Payé', 'money'),
                    $this->col('Disponible', 'money'), $this->col('Taux exéc.', 'percent'),
                ],
                'lignes' => $rows->map(fn ($r) => [
                    trim(($r['code'] ?? '').' '.($r['intitule'] ?? '')),
                    $r['ouvert'], $r['engage'], $r['liquide'], $r['paye'], $r['disponible'], $r['taux_execution'],
                ])->all(),
                'total' => [
                    'TOTAL DÉPENSES', $rows->sum('ouvert'), $rows->sum('engage'), $rows->sum('liquide'),
                    $rows->sum('paye'), $rows->sum('disponible'), $this->tauxGlobal($rows),
                ],
            ],
        ];
    }

    private function rbmGar(Exercice $ex): array
    {
        $rows = $this->svc->rbmGar($ex->id);
        $exec = app(\App\Services\Pap\PapPerformanceService::class)->executionPap($ex->id);
        $lignesAct = $rows->where('activite', true);

        $fmtFeu = fn (?string $feu) => match ($feu) {
            'vert' => 'Vert',
            'orange' => 'Orange',
            'rouge' => 'Rouge',
            default => '—',
        };

        return [
            [
                'type' => 'kpi',
                'titre' => 'Synthèse exécution PAP (taux financier réel)',
                'items' => [
                    ['label' => 'Activités', 'valeur' => $lignesAct->pluck('activite_id')->unique()->count(), 'fmt' => 'int'],
                    ['label' => 'Indicateurs IOV', 'valeur' => $lignesAct->count(), 'fmt' => 'int'],
                    ['label' => 'Taux physique moyen', 'valeur' => $exec['taux_physique'], 'fmt' => 'percent'],
                    ['label' => 'Taux financier pondéré', 'valeur' => $exec['taux_financier'], 'fmt' => 'percent'],
                    ['label' => 'Montant payé (réel)', 'valeur' => $exec['montant_paye'], 'fmt' => 'money'],
                    ['label' => 'IOV en alerte rouge', 'valeur' => $lignesAct->where('feu', 'rouge')->count(), 'fmt' => 'int'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Cadre de mesure du rendement (Pilier → Activité · tous les IOV)',
                'colonnes' => [
                    $this->col('Code / Intitulé'),
                    $this->col('Code IOV'),
                    $this->col('Indicateur (IOV)'),
                    $this->col('Source vérif.'),
                    $this->col('Période'),
                    $this->col('Cible', 'num'),
                    $this->col('Réal.', 'num'),
                    $this->col('Feu'),
                    $this->col('Taux phys.', 'percent'),
                    $this->col('Budget prévu', 'money'),
                    $this->col('Engagé', 'money'),
                    $this->col('Payé (réel)', 'money'),
                    $this->col('Taux fin.', 'percent'),
                ],
                'lignes' => $rows->map(fn ($r) => [
                    trim($r['code'].' — '.$r['intitule']),
                    $r['iov_code'] ?? '',
                    $r['iov'] ?? '',
                    $r['source'] ?? '',
                    $r['periode'] ?? '',
                    $r['cible'],
                    $r['realisation'],
                    $fmtFeu($r['feu'] ?? null),
                    $r['premiere_ligne'] ? $r['taux_physique'] : null,
                    $r['premiere_ligne'] ? $r['prevu'] : null,
                    $r['premiere_ligne'] ? $r['engage'] : null,
                    $r['premiere_ligne'] ? $r['paye'] : null,
                    $r['premiere_ligne'] ? $r['taux_financier'] : null,
                ])->all(),
                'niveaux' => $rows->pluck('niveau')->all(),
                'total' => null,
            ],
        ];
    }

    private function pap(Exercice $ex, array $params): array
    {
        $d = $this->svc->papPerformance($ex->id, $params['structure_id'] ?? null);
        $perf = app(\App\Services\Pap\PapPerformanceService::class);

        $sections = [
            [
                'type' => 'text', 'titre' => '1. Résumé exécutif',
                'contenu' => "Le présent rapport synthétise l'exécution du Plan Annuel de Performance de l'exercice {$ex->annee}. "
                    ."Il porte sur {$d['nb_activites']} activité(s). Le taux de réalisation physique moyen s'établit à {$d['physique_moyen']}% "
                    ."et le taux de réalisation financière à {$d['financier_moyen']}%.",
            ],
            [
                'type' => 'kpi', 'titre' => '2. Indicateurs clés',
                'items' => [
                    ['label' => 'Activités suivies', 'valeur' => $d['nb_activites'], 'fmt' => 'int'],
                    ['label' => 'Réalisation physique', 'valeur' => $d['physique_moyen'], 'fmt' => 'percent'],
                    ['label' => 'Réalisation financière', 'valeur' => $d['financier_moyen'], 'fmt' => 'percent'],
                    ['label' => 'Marchés (PPM)', 'valeur' => $d['marches']->count(), 'fmt' => 'int'],
                ],
            ],
            [
                'type' => 'table', 'titre' => '3. Réalisations physiques par activité',
                'colonnes' => [$this->col('Code'), $this->col('Activité'), $this->col('Taux phys.', 'percent'), $this->col('Taux fin.', 'percent')],
                'lignes' => $d['activites']->map(fn ($a) => [
                    $a->code,
                    $a->intitule,
                    (float) $a->taux_realisation_physique,
                    $perf->tauxFinancierReel($a),
                ])->all(),
                'total' => null,
            ],
            [
                'type' => 'table', 'titre' => '4. Réalisations financières (exécution budgétaire par axe)',
                'colonnes' => [$this->col('Axe'), $this->col('Prévisions', 'money'), $this->col('Engagé', 'money'), $this->col('Payé', 'money'), $this->col('Taux exéc.', 'percent')],
                'lignes' => $this->svc->parAxe($ex->id)->map(fn ($r) => [trim(($r['code'] ?? '').' '.($r['intitule'] ?? '')), $r['ouvert'], $r['engage'], $r['paye'], $r['taux_execution']])->all(),
                'total' => null,
            ],
            [
                'type' => 'table', 'titre' => '5. Exécution du Plan de Passation des Marchés (PPM)',
                'colonnes' => [$this->col('N° engagement'), $this->col('Objet'), $this->col('Bénéficiaire'), $this->col('Montant', 'money'), $this->col('Statut')],
                'lignes' => $d['marches']->map(fn ($m) => [$m->numero, $m->objet, $m->beneficiaire ?? '—', (float) $m->montant_total, $m->statut])->all(),
                'total' => null,
            ],
            [
                'type' => 'liste', 'titre' => '6. Contraintes identifiées (activités en retard, taux physique < 50%)',
                'items' => $d['contraintes']->map(fn ($a) => "{$a->code} — {$a->intitule} (".(float) $a->taux_realisation_physique.'%)')->all()
                    ?: ['Aucune contrainte majeure relevée.'],
            ],
            [
                'type' => 'liste', 'titre' => '7. Opportunités (activités à fort taux de réalisation ≥ 90%)',
                'items' => $d['opportunites']->map(fn ($a) => "{$a->code} — {$a->intitule}")->all()
                    ?: ['—'],
            ],
            [
                'type' => 'liste', 'titre' => '8. Recommandations (indicateurs au rouge)',
                'items' => collect($d['recommandations'])->map(fn ($r) => "{$r->libelle} (réalisé {$r->valeur_realisee} / cible {$r->valeur_cible})")->all()
                    ?: ['Maintenir le rythme actuel et consolider les acquis.'],
            ],
        ];

        return $sections;
    }

    private function marches(Exercice $ex): array
    {
        $rows = $this->svc->marchesPpm($ex->id);

        return [
            [
                'type' => 'kpi', 'titre' => 'Synthèse du PPM',
                'items' => [
                    ['label' => 'Marchés', 'valeur' => $rows->count(), 'fmt' => 'int'],
                    ['label' => 'Montant estimé', 'valeur' => $rows->sum('estime'), 'fmt' => 'money'],
                    ['label' => 'Montant attribué', 'valeur' => $rows->sum('attribue'), 'fmt' => 'money'],
                    ['label' => 'Attribués/signés', 'valeur' => $rows->whereIn('statut', ['Attribué', 'Signé', 'Exécuté'])->count(), 'fmt' => 'int'],
                ],
            ],
            [
                'type' => 'table', 'titre' => 'Plan de passation des marchés',
                'colonnes' => [
                    $this->col('N°'), $this->col('Objet'), $this->col('Mode'), $this->col('Type'),
                    $this->col('Estimé', 'money'), $this->col('Attribué', 'money'), $this->col('Taux attr.', 'percent'), $this->col('Statut'),
                ],
                'lignes' => $rows->map(fn ($r) => [$r['numero'], $r['objet'], $r['mode'], $r['type'], $r['estime'], $r['attribue'], $r['taux_attribution'], $r['statut']])->all(),
                'total' => ['TOTAL', '', '', '', $rows->sum('estime'), $rows->sum('attribue'), '', ''],
            ],
        ];
    }

    private function contrats(Exercice $ex): array
    {
        $rows = $this->svc->contratsRegistre($ex->id);

        return [
            [
                'type' => 'kpi', 'titre' => 'Synthèse des contrats',
                'items' => [
                    ['label' => 'Contrats', 'valeur' => $rows->count(), 'fmt' => 'int'],
                    ['label' => 'Montant contractuel', 'valeur' => $rows->sum('montant'), 'fmt' => 'money'],
                    ['label' => 'Engagé', 'valeur' => $rows->sum('engage'), 'fmt' => 'money'],
                    ['label' => 'Payé', 'valeur' => $rows->sum('paye'), 'fmt' => 'money'],
                ],
            ],
            [
                'type' => 'table', 'titre' => 'Registre des contrats (Besoin → Engagement → Marché → Contrat → Paiement)',
                'colonnes' => [
                    $this->col('Contrat'), $this->col('Titulaire'), $this->col('Besoin'), $this->col('Engagement'),
                    $this->col('Marché'), $this->col('Montant', 'money'), $this->col('Engagé', 'money'), $this->col('Payé', 'money'), $this->col('Statut'),
                ],
                'lignes' => $rows->map(fn ($r) => [$r['contrat'], $r['titulaire'], $r['besoin'], $r['engagement'], $r['marche'], $r['montant'], $r['engage'], $r['paye'], $r['statut']])->all(),
                'total' => ['TOTAL', '', '', '', '', $rows->sum('montant'), $rows->sum('engage'), $rows->sum('paye'), ''],
            ],
        ];
    }

    private function recommandations(Exercice $ex): array
    {
        $d = $this->svc->recommandations($ex->id);
        $detail = $d['detail'];

        return [
            [
                'type' => 'kpi', 'titre' => 'Synthèse du suivi',
                'items' => [
                    ['label' => 'Recommandations', 'valeur' => $detail->count(), 'fmt' => 'int'],
                    ['label' => 'Réalisées', 'valeur' => $detail->where('statut', 'Réalisée')->count(), 'fmt' => 'int'],
                    ['label' => 'En cours', 'valeur' => $detail->where('statut', 'En cours')->count(), 'fmt' => 'int'],
                    ['label' => "Taux d'exécution moyen", 'valeur' => $detail->count() ? round($detail->avg('taux'), 1) : 0, 'fmt' => 'percent'],
                ],
            ],
            [
                'type' => 'table', 'titre' => 'Synthèse par source',
                'colonnes' => [$this->col('Source'), $this->col('Total', 'int'), $this->col('Réalisées', 'int'), $this->col('Taux moyen', 'percent')],
                'lignes' => $d['parSource']->map(fn ($r) => [$r['source'], $r['total'], $r['realisees'], $r['taux_moyen']])->all(),
                'total' => null,
            ],
            [
                'type' => 'table', 'titre' => 'Détail des recommandations',
                'colonnes' => [
                    $this->col('N°'), $this->col('Source'), $this->col('Libellé'), $this->col('Structure'),
                    $this->col('Priorité'), $this->col('Échéance'), $this->col('Statut'), $this->col('Taux', 'percent'),
                ],
                'lignes' => $detail->map(fn ($r) => [$r['numero'], $r['source'], $r['libelle'], $r['structure'], $r['priorite'], $r['echeance'], $r['statut'], $r['taux']])->all(),
                'total' => null,
            ],
        ];
    }

    private function formations(Exercice $ex): array
    {
        $d = $this->svc->formations($ex->id);
        $detail = $d['detail'];

        return [
            [
                'type' => 'kpi', 'titre' => 'Synthèse',
                'items' => [
                    ['label' => 'Sessions', 'valeur' => $detail->count(), 'fmt' => 'int'],
                    ['label' => 'Participants', 'valeur' => $detail->sum('participants'), 'fmt' => 'int'],
                    ['label' => 'Hommes / Femmes', 'valeur' => $d['hommes'].' / '.$d['femmes'], 'fmt' => 'text'],
                    ['label' => 'Coût total', 'valeur' => $d['cout_total'], 'fmt' => 'money'],
                ],
            ],
            [
                'type' => 'table', 'titre' => 'Synthèse par type',
                'colonnes' => [$this->col('Type'), $this->col('Sessions', 'int'), $this->col('Participants', 'int'), $this->col('Coût', 'money')],
                'lignes' => $d['parType']->map(fn ($r) => [$r['type'], $r['sessions'], $r['participants'], $r['cout']])->all(),
                'total' => ['TOTAL', $detail->count(), $detail->sum('participants'), $d['cout_total']],
            ],
            [
                'type' => 'table', 'titre' => 'Détail des sessions (participation, genre, coûts, satisfaction)',
                'colonnes' => [
                    $this->col('N°'), $this->col('Intitulé'), $this->col('Type'), $this->col('Particip.', 'int'),
                    $this->col('H', 'int'), $this->col('F', 'int'), $this->col('Coût', 'money'), $this->col('Satisf.', 'percent'), $this->col('Statut'),
                ],
                'lignes' => $detail->map(fn ($r) => [$r['numero'], $r['intitule'], $r['type'], $r['participants'], $r['hommes'], $r['femmes'], $r['cout'], $r['satisfaction'], $r['statut']])->all(),
                'total' => ['TOTAL', '', '', $detail->sum('participants'), $d['hommes'], $d['femmes'], $d['cout_total'], '', ''],
            ],
        ];
    }

    private function immobilisations(Exercice $ex): array
    {
        $d = $this->svc->immobilisations($ex->id);
        $detail = $d['detail'];

        return [
            [
                'type' => 'kpi', 'titre' => 'Synthèse patrimoniale',
                'items' => [
                    ['label' => 'Biens enregistrés', 'valeur' => $detail->count(), 'fmt' => 'int'],
                    ['label' => 'Valeur d\'acquisition', 'valeur' => $d['total_acquisition'], 'fmt' => 'money'],
                    ['label' => 'Amortissement cumulé', 'valeur' => $d['total_amortissement'], 'fmt' => 'money'],
                    ['label' => 'Valeur nette comptable', 'valeur' => $d['total_valeur_nette'], 'fmt' => 'money'],
                ],
            ],
            [
                'type' => 'table', 'titre' => 'Synthèse par catégorie',
                'colonnes' => [
                    $this->col('Catégorie'), $this->col('Nombre', 'int'),
                    $this->col('Acquisition', 'money'), $this->col('Amortissement', 'money'), $this->col('VNC', 'money'),
                ],
                'lignes' => $d['parCategorie']->map(fn ($r) => [$r['categorie'], $r['nombre'], $r['acquisition'], $r['amortissement'], $r['valeur_nette']])->all(),
                'total' => ['TOTAL', $detail->count(), $d['total_acquisition'], $d['total_amortissement'], $d['total_valeur_nette']],
            ],
            [
                'type' => 'table', 'titre' => 'Détail des immobilisations',
                'colonnes' => [
                    $this->col('N°'), $this->col('Désignation'), $this->col('Catégorie'), $this->col('Structure'),
                    $this->col('Acquisition', 'money'), $this->col('Amort.', 'money'), $this->col('VNC', 'money'), $this->col('Statut'),
                ],
                'lignes' => $detail->map(fn ($r) => [$r['numero'], $r['designation'], $r['categorie'], $r['structure'], $r['valeur_acquisition'], $r['amortissement_cumule'], $r['valeur_nette'], $r['statut']])->all(),
                'total' => ['TOTAL', '', '', '', $d['total_acquisition'], $d['total_amortissement'], $d['total_valeur_nette'], ''],
            ],
        ];
    }

    private function techniqueSante(): array
    {
        $d = $this->techniqueSvc->sante();
        $m = $d['mesures'];

        return [
            [
                'type' => 'kpi', 'titre' => 'État global',
                'items' => [
                    ['label' => 'Statut', 'valeur' => strtoupper($d['statut_global']), 'fmt' => 'text'],
                    ['label' => 'Jobs en attente', 'valeur' => $m['jobs_en_attente'] ?? 0, 'fmt' => 'int'],
                    ['label' => 'Jobs échoués', 'valeur' => $m['jobs_echoues'] ?? 0, 'fmt' => 'int'],
                    ['label' => 'Disque libre', 'valeur' => ($m['disque_libre_pct'] ?? 0).'%', 'fmt' => 'text'],
                ],
            ],
            [
                'type' => 'table', 'titre' => 'Contrôles infrastructure',
                'colonnes' => [$this->col('Contrôle'), $this->col('Statut'), $this->col('Valeur'), $this->col('Détail')],
                'lignes' => collect($d['controles'])->map(fn ($c) => [$c['libelle'], $c['statut'], $c['valeur'], $c['detail'] ?? ''])->all(),
                'total' => null,
            ],
            [
                'type' => 'table', 'titre' => 'Jobs échoués récents',
                'colonnes' => [$this->col('UUID'), $this->col('File'), $this->col('Échec'), $this->col('Date')],
                'lignes' => $d['jobs_echoues']->map(fn ($j) => [
                    $j->uuid ?? '—', $j->queue ?? '—',
                    \Illuminate\Support\Str::limit($j->exception ?? '', 80),
                    $j->failed_at ?? '—',
                ])->all(),
                'total' => null,
            ],
        ];
    }

    private function techniqueIntegrite(Exercice $ex): array
    {
        $d = $this->techniqueSvc->integrite($ex->id);
        $synth = $d['synthese'] ?? [];
        $modules = collect($d['modules'] ?? []);
        $anomalies = collect($d['anomalies'] ?? []);

        return [
            [
                'type' => 'kpi', 'titre' => 'Score de conformité',
                'items' => [
                    ['label' => 'Score global', 'valeur' => $synth['score_conformite'] ?? 0, 'fmt' => 'int'],
                    ['label' => 'Anomalies totales', 'valeur' => $synth['anomalies_total'] ?? 0, 'fmt' => 'int'],
                    ['label' => 'Critiques', 'valeur' => $synth['anomalies_critiques'] ?? 0, 'fmt' => 'int'],
                    ['label' => 'Modules exécutés', 'valeur' => count($d['modules_executes'] ?? []), 'fmt' => 'int'],
                ],
            ],
            [
                'type' => 'table', 'titre' => 'Résultats par module',
                'colonnes' => [$this->col('Module'), $this->col('Anomalies', 'int'), $this->col('Critiques', 'int'), $this->col('Score', 'percent')],
                'lignes' => $modules->map(fn ($m, $nom) => [$nom, $m['anomalies_total'] ?? 0, $m['anomalies_critiques'] ?? 0, $m['score'] ?? 0])->values()->all(),
                'total' => null,
            ],
            [
                'type' => 'table', 'titre' => 'Anomalies détectées (extrait)',
                'colonnes' => [$this->col('Module'), $this->col('Niveau'), $this->col('Code'), $this->col('Message')],
                'lignes' => $anomalies->take(50)->map(fn ($a) => [$a['module'] ?? '—', $a['niveau'] ?? '—', $a['code'] ?? '—', $a['message'] ?? ''])->all(),
                'total' => null,
            ],
        ];
    }

    private function techniqueAudit(): array
    {
        $d = $this->techniqueSvc->audit();
        $totalEnreg = $d['tables']->sum('enregistrements');

        return [
            [
                'type' => 'kpi', 'titre' => 'Synthèse journaux',
                'items' => [
                    ['label' => 'Tables auditées', 'valeur' => $d['tables']->count(), 'fmt' => 'int'],
                    ['label' => 'Enregistrements totaux', 'valeur' => $totalEnreg, 'fmt' => 'int'],
                    ['label' => 'Actions (7 j)', 'valeur' => $d['activite']->sum('total'), 'fmt' => 'int'],
                ],
            ],
            [
                'type' => 'table', 'titre' => 'Volumes et rétention par table',
                'colonnes' => [$this->col('Table'), $this->col('Rétention (j)', 'int'), $this->col('Volume', 'int'), $this->col('Plus ancien')],
                'lignes' => $d['tables']->map(fn ($t) => [$t['table'], $t['retention_jours'], $t['enregistrements'], $t['plus_ancien']])->all(),
                'total' => ['TOTAL', '', $totalEnreg, ''],
            ],
            [
                'type' => 'table', 'titre' => 'Activité audit (7 derniers jours)',
                'colonnes' => [$this->col('Action'), $this->col('Occurrences', 'int')],
                'lignes' => $d['activite']->map(fn ($a) => [$a['action'], $a['total']])->all(),
                'total' => null,
            ],
        ];
    }

    private function nomenclature(): array
    {
        $d = $this->svc->nomenclature();
        $c = $d['comptes'];

        return [
            [
                'type' => 'kpi', 'titre' => 'Synthèse de la nomenclature',
                'items' => [
                    ['label' => 'Titres', 'valeur' => $c['titres'], 'fmt' => 'int'],
                    ['label' => 'Articles', 'valeur' => $c['articles'], 'fmt' => 'int'],
                    ['label' => 'Paragraphes', 'valeur' => $c['paragraphes'], 'fmt' => 'int'],
                    ['label' => 'Rubriques', 'valeur' => $c['rubriques'], 'fmt' => 'int'],
                ],
            ],
            [
                'type' => 'table', 'titre' => 'Synthèse par titre',
                'colonnes' => [$this->col('Sens'), $this->col('Titre'), $this->col('Intitulé'), $this->col('Rubriques', 'int')],
                'lignes' => $d['parTitre']->map(fn ($t) => [$t['sens'], $t['code'], $t['intitule'], $t['rubriques']])->all(),
                'total' => null,
            ],
            [
                'type' => 'table', 'titre' => 'Nomenclature complète',
                'colonnes' => [
                    $this->col('Sens'), $this->col('Titre'), $this->col('Article'),
                    $this->col('Paragraphe'), $this->col('Rubrique'), $this->col('Intitulé'),
                ],
                'lignes' => $d['detail']->map(fn ($r) => [$r['sens'], $r['titre'], $r['article'], $r['paragraphe'], $r['rubrique'], $r['intitule']])->all(),
                'total' => null,
            ],
        ];
    }

    private function budgetInstitutionnel(Exercice $ex): array
    {
        $svc = app(\App\Services\Budget\BudgetReportService::class);

        return $svc->sectionsRapport($ex);
    }

    private function situationBudgetaire(Exercice $ex): array
    {
        $global = $this->svc->global($ex->id);
        $structures = $this->svc->parStructure($ex->id, 20);
        $bailleur = $this->svc->parBailleur($ex->id);

        return [
            $this->kpiGlobal($global),
            [
                'type' => 'kpi',
                'titre' => 'Restes et disponibilités',
                'items' => [
                    ['label' => 'Crédits disponibles', 'valeur' => $global['disponible'], 'fmt' => 'money'],
                    ['label' => 'Restes à engager', 'valeur' => $global['reste_a_engager'], 'fmt' => 'money'],
                    ['label' => 'Restes à payer', 'valeur' => $global['reste_a_payer'], 'fmt' => 'money'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Situation par structure',
                'colonnes' => [
                    $this->col('Structure'),
                    $this->col('Ouvert', 'money'),
                    $this->col('Engagé', 'money'),
                    $this->col('Payé', 'money'),
                ],
                'lignes' => $structures->map(fn ($r) => [$r['libelle'], $r['ouvert'], $r['engage'], $r['paye']])->all(),
            ],
            [
                'type' => 'table',
                'titre' => 'Situation par source de financement',
                'colonnes' => [
                    $this->col('Source'),
                    $this->col('Ouvert', 'money'),
                    $this->col('Engagé', 'money'),
                    $this->col('Payé', 'money'),
                    $this->col('Taux exéc.', 'percent'),
                ],
                'lignes' => $bailleur['sources']->map(fn ($r) => [
                    $r['libelle'], $r['ouvert'], $r['engage'], $r['paye'], $r['taux_execution'],
                ])->all(),
            ],
        ];
    }

    private function indicateursEvolution(Exercice $ex): array
    {
        $rows = app(\App\Services\Rbm\IndicateurPerformanceService::class)->evolutionExercice($ex->id);
        $feux = app(\App\Services\Rbm\IndicateurPerformanceService::class)->feuxGlobaux($ex->id);

        return [
            [
                'type' => 'kpi',
                'titre' => 'Feux de performance IOV',
                'items' => [
                    ['label' => 'Vert', 'valeur' => $feux['vert'], 'fmt' => 'int'],
                    ['label' => 'Orange', 'valeur' => $feux['orange'], 'fmt' => 'int'],
                    ['label' => 'Rouge', 'valeur' => $feux['rouge'], 'fmt' => 'int'],
                    ['label' => 'Indicateurs suivis', 'valeur' => $rows->count(), 'fmt' => 'int'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Évolution et écarts par indicateur',
                'colonnes' => [
                    $this->col('Code'),
                    $this->col('Libellé'),
                    $this->col('Dernière période'),
                    $this->col('Cible', 'num'),
                    $this->col('Réalisé', 'num'),
                    $this->col('Écart cible', 'num'),
                    $this->col('Taux', 'percent'),
                    $this->col('Feu'),
                    $this->col('Var. N-1', 'percent'),
                ],
                'lignes' => $rows->map(fn ($r) => [
                    $r['code'],
                    $r['libelle'],
                    $r['derniere_periode'] ?? '—',
                    $r['cible'],
                    $r['valeur_realisee'],
                    $r['ecart_cible'],
                    $r['taux_atteinte'],
                    $r['feu'] ?? '—',
                    $r['variation_pct'],
                ])->all(),
            ],
        ];
    }

    private function ecartsPhysiqueFinancier(Exercice $ex): array
    {
        $rows = app(\App\Services\Pap\PapPerformanceService::class)->ecartsPhysiqueFinancier($ex->id);
        $sur = $rows->where('type_ecart', 'sur-financée')->count();
        $sous = $rows->where('type_ecart', 'sous-financée')->count();
        $align = $rows->where('type_ecart', 'alignée')->count();

        $campagnes = CampagneSuivi::where('exercice_id', $ex->id)->get();
        $campagneIds = $campagnes->pluck('id');
        $collectesTotal = $campagneIds->isEmpty() ? 0 : Collecte::whereIn('campagne_id', $campagneIds)->count();
        $collectesValidees = $campagneIds->isEmpty() ? 0 : Collecte::whereIn('campagne_id', $campagneIds)->where('statut', 'validee')->count();
        $activitesTotal = $rows->count();
        $activitesCouvertes = $campagneIds->isEmpty() ? 0 : Collecte::whereIn('campagne_id', $campagneIds)
            ->where('statut', 'validee')
            ->distinct('activite_id')
            ->count('activite_id');
        $tauxCouverture = $activitesTotal > 0 ? round($activitesCouvertes / $activitesTotal * 100, 1) : 0.0;

        return [
            [
                'type' => 'kpi',
                'titre' => 'Campagnes de suivi physique (S&E)',
                'items' => [
                    ['label' => 'Campagnes exercice', 'valeur' => $campagnes->count(), 'fmt' => 'int'],
                    ['label' => 'Collectes saisies', 'valeur' => $collectesTotal, 'fmt' => 'int'],
                    ['label' => 'Collectes validées', 'valeur' => $collectesValidees, 'fmt' => 'int'],
                    ['label' => 'Couverture activités (collectes validées)', 'valeur' => $tauxCouverture, 'fmt' => 'percent'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Campagnes de collecte sur l\'exercice',
                'colonnes' => [
                    $this->col('Période'),
                    $this->col('Intitulé'),
                    $this->col('Statut'),
                    $this->col('Collectes', 'int'),
                    $this->col('Validées', 'int'),
                ],
                'lignes' => $campagnes->map(function (CampagneSuivi $c) {
                    $total = $c->collectes()->count();
                    $val = $c->collectes()->where('statut', 'validee')->count();

                    return [$c->periode ?? '—', $c->libelle, $c->statut, $total, $val];
                })->all() ?: [['—', 'Aucune campagne', '—', 0, 0]],
            ],
            [
                'type' => 'kpi',
                'titre' => 'Synthèse des écarts PAP',
                'items' => [
                    ['label' => 'Activités analysées', 'valeur' => $rows->count(), 'fmt' => 'int'],
                    ['label' => 'Sur-financées', 'valeur' => $sur, 'fmt' => 'int'],
                    ['label' => 'Sous-financées', 'valeur' => $sous, 'fmt' => 'int'],
                    ['label' => 'Alignées', 'valeur' => $align, 'fmt' => 'int'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Écarts physique / financier par activité',
                'colonnes' => [
                    $this->col('Code'),
                    $this->col('Activité'),
                    $this->col('Taux phys.', 'percent'),
                    $this->col('Taux fin.', 'percent'),
                    $this->col('Écart', 'percent'),
                    $this->col('Type'),
                    $this->col('Prévu', 'money'),
                    $this->col('Payé', 'money'),
                ],
                'lignes' => $rows->map(fn ($r) => [
                    $r['code'],
                    $r['intitule'],
                    $r['taux_physique'],
                    $r['taux_financier'],
                    $r['ecart'],
                    $r['type_ecart'],
                    $r['montant_prevu'],
                    $r['montant_paye'],
                ])->all(),
            ],
        ];
    }

    private function suiviEvaluation(Exercice $ex): array
    {
        $d = $this->svc->suiviEvaluation($ex->id);
        $s = $d['scores_courants'];

        return [
            [
                'type' => 'kpi',
                'titre' => 'Score global S&E',
                'items' => [
                    ['label' => 'Score global', 'valeur' => $s['score_global'], 'fmt' => 'percent'],
                    ['label' => 'Niveau', 'valeur' => EvaluationPeriodique::NIVEAUX[$d['niveau']] ?? $d['niveau'], 'fmt' => 'text'],
                    ['label' => 'Physique PAP', 'valeur' => $s['score_physique'], 'fmt' => 'percent'],
                    ['label' => 'Indicateurs IOV', 'valeur' => $s['score_iov'], 'fmt' => 'percent'],
                    ['label' => 'Recommandations', 'valeur' => $s['score_recommandations'], 'fmt' => 'percent'],
                    ['label' => 'Formations', 'valeur' => $s['score_formations'], 'fmt' => 'percent'],
                    ['label' => 'Conformité suivi', 'valeur' => $s['score_conformite'], 'fmt' => 'percent'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Historique des évaluations périodiques',
                'colonnes' => [
                    $this->col('Période'),
                    $this->col('Type'),
                    $this->col('Score global', 'percent'),
                    $this->col('Niveau'),
                    $this->col('Généré le'),
                ],
                'lignes' => collect($d['historique'])->map(fn ($r) => [
                    $r['periode'], $r['type'], $r['score_global'], $r['niveau'], $r['genere_le'],
                ])->all(),
            ],
            [
                'type' => 'kpi',
                'titre' => 'Chaîne S&E (missions & plans)',
                'items' => [
                    ['label' => 'Missions en cours', 'valeur' => $d['missions']['en_cours'] ?? 0, 'fmt' => 'int'],
                    ['label' => 'Plans en cours', 'valeur' => $d['plans']['en_cours'] ?? 0, 'fmt' => 'int'],
                    ['label' => 'Taux moyen plans', 'valeur' => $d['plans']['taux_moyen'] ?? 0, 'fmt' => 'percent'],
                ],
            ],
        ];
    }

    /** Mobilisation des contributions — fédération module Recettes dans le hub Reporting. */
    private function recettesMobilisation(Exercice $ex): array
    {
        $svc = app(\App\Services\Recettes\RecettesReportingService::class);
        $rows = $svc->mobilisationParEtat($ex->id);
        $synth = $svc->syntheseMobilisation($ex->id);

        return [
            [
                'type' => 'kpi',
                'titre' => 'Synthèse mobilisation',
                'items' => [
                    ['label' => 'Contributions appelées', 'valeur' => $synth['appele'], 'fmt' => 'money'],
                    ['label' => 'Contributions recouvrées', 'valeur' => $synth['recouvre'], 'fmt' => 'money'],
                    ['label' => 'Arriérés', 'valeur' => $synth['arriere'], 'fmt' => 'money'],
                    ['label' => 'Taux de recouvrement', 'valeur' => $synth['taux'], 'fmt' => 'percent'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Mobilisation par État membre',
                'colonnes' => [
                    $this->col('État'),
                    $this->col('Code'),
                    $this->col('Clé répart.', 'percent'),
                    $this->col('Appelé', 'money'),
                    $this->col('Recouvré', 'money'),
                    $this->col('Arriéré', 'money'),
                    $this->col('Taux', 'percent'),
                ],
                'lignes' => $rows->map(fn ($r) => [$r->pays, $r->code, $r->cle_repartition, $r->appele, $r->recouvre, $r->arriere, $r->taux])->all(),
                'total' => ['TOTAL', '', '', $synth['appele'], $synth['recouvre'], $synth['arriere'], $synth['taux']],
            ],
        ];
    }

    /** Rapport financier consolidé des recettes — fédération hub Reporting. */
    private function recettesFinancier(Exercice $ex): array
    {
        $reporting = app(\App\Services\Recettes\RecettesReportingService::class);
        $gestion = app(\App\Services\Recettes\GestionRecettesService::class);
        $synthesePrev = $gestion->syntheseExercice($ex->id);
        $mobilisation = $reporting->mobilisationParEtat($ex->id);
        $synthMob = $reporting->syntheseMobilisation($ex->id);
        $arrieres = $reporting->arrieres($ex->id);

        return [
            [
                'type' => 'kpi',
                'titre' => 'Synthèse des recettes',
                'items' => [
                    ['label' => 'Recettes prévues', 'valeur' => $synthesePrev['montant_prevu'] ?? 0, 'fmt' => 'money'],
                    ['label' => 'Recettes réalisées', 'valeur' => $synthesePrev['montant_realise'] ?? 0, 'fmt' => 'money'],
                    ['label' => 'Contributions appelées', 'valeur' => $synthMob['appele'], 'fmt' => 'money'],
                    ['label' => 'Contributions recouvrées', 'valeur' => $synthMob['recouvre'], 'fmt' => 'money'],
                    ['label' => 'Arriérés', 'valeur' => $synthMob['arriere'], 'fmt' => 'money'],
                    ['label' => 'Taux de recouvrement', 'valeur' => $synthMob['taux'], 'fmt' => 'percent'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Mobilisation par État membre',
                'colonnes' => [
                    $this->col('État'),
                    $this->col('Appelé', 'money'),
                    $this->col('Recouvré', 'money'),
                    $this->col('Arriéré', 'money'),
                    $this->col('Taux', 'percent'),
                ],
                'lignes' => $mobilisation->map(fn ($r) => [$r->pays, $r->appele, $r->recouvre, $r->arriere, $r->taux])->all(),
                'total' => ['TOTAL', $synthMob['appele'], $synthMob['recouvre'], $synthMob['arriere'], $synthMob['taux']],
            ],
            [
                'type' => 'table',
                'titre' => 'Arriérés en cours',
                'colonnes' => [
                    $this->col('État'),
                    $this->col('Référence'),
                    $this->col('Appelé', 'money'),
                    $this->col('Réglé', 'money'),
                    $this->col('Solde', 'money'),
                ],
                'lignes' => $arrieres->take(25)->map(fn ($a) => [
                    $a->etatMembre?->pays ?? '—',
                    $a->reference ?? '—',
                    (float) $a->montant_appele,
                    (float) $a->montant_regle,
                    max((float) $a->montant_appele - (float) $a->montant_regle, 0),
                ])->all() ?: [['—', 'Aucun arriéré', 0, 0, 0]],
            ],
        ];
    }

    // ---- Helpers ----------------------------------------------------------------------------

    /** Rapport flux de trésorerie unifié recettes / dépenses (P2-6). */
    private function tresorerieFlux(Exercice $ex): array
    {
        $tresorerie = app(\App\Services\Tresorerie\TresorerieService::class);
        $s = $tresorerie->synthese($ex->id);
        $encaissements = $tresorerie->journalEncaissements($ex->id);
        $paiements = $tresorerie->journalPaiements($ex->id);

        $sections = [
            [
                'type' => 'kpi',
                'titre' => 'Position de trésorerie',
                'items' => [
                    ['label' => 'Encaissements validés', 'valeur' => $s['flux']['encaissements'], 'fmt' => 'money'],
                    ['label' => 'Décaissements (paiements validés)', 'valeur' => $s['flux']['decaissements'], 'fmt' => 'money'],
                    ['label' => 'Flux net', 'valeur' => $s['flux']['net'], 'fmt' => 'money'],
                    ['label' => 'Couverture des paiements', 'valeur' => $s['flux']['couverture_paiements'] ?? 0, 'fmt' => 'percent'],
                    ['label' => 'Reste à recouvrer (contributions)', 'valeur' => $s['recettes']['reste_a_recouvrer'], 'fmt' => 'money'],
                    ['label' => 'Reste à payer (budget)', 'valeur' => $s['depenses']['reste_a_payer'], 'fmt' => 'money'],
                ],
            ],
        ];

        if ($s['equilibre']['alerte']) {
            $sections[] = ['type' => 'text', 'titre' => 'Alerte', 'contenu' => $s['equilibre']['alerte']];
        }

        if ($s['evolution_mensuelle'] !== []) {
            $sections[] = [
                'type' => 'table',
                'titre' => 'Évolution mensuelle des flux',
                'colonnes' => [
                    $this->col('Mois'),
                    $this->col('Encaissements', 'money'),
                    $this->col('Décaissements', 'money'),
                    $this->col('Net', 'money'),
                ],
                'lignes' => collect($s['evolution_mensuelle'])->map(fn ($l) => [
                    $l['mois'], $l['entrees'], $l['sorties'], $l['net'],
                ])->all(),
                'total' => [
                    'TOTAL',
                    collect($s['evolution_mensuelle'])->sum('entrees'),
                    collect($s['evolution_mensuelle'])->sum('sorties'),
                    collect($s['evolution_mensuelle'])->sum('net'),
                ],
            ];
        }

        $sections[] = [
            'type' => 'table',
            'titre' => 'Journal des encaissements',
            'colonnes' => [
                $this->col('Référence'),
                $this->col('Recette'),
                $this->col('Date'),
                $this->col('Montant', 'money'),
                $this->col('Mode'),
                $this->col('Statut'),
            ],
            'lignes' => $encaissements->map(fn ($r) => [
                $r['reference'], $r['recette'], $r['date'], $r['montant'], $r['mode'], $r['statut'],
            ])->all(),
            'total' => ['TOTAL', '', '', $encaissements->sum('montant'), '', ''],
        ];

        $sections[] = [
            'type' => 'table',
            'titre' => 'Journal des décaissements (paiements)',
            'colonnes' => [
                $this->col('N° paiement'),
                $this->col('Engagement'),
                $this->col('Bénéficiaire'),
                $this->col('Date'),
                $this->col('Montant', 'money'),
                $this->col('Mode'),
            ],
            'lignes' => $paiements->map(fn ($r) => [
                $r['numero'], $r['engagement'], $r['beneficiaire'], $r['date'], $r['montant'], $r['mode'],
            ])->all(),
            'total' => ['TOTAL', '', '', '', $paiements->sum('montant'), ''],
        ];

        return $sections;
    }

    /** Dette fournisseurs consolidée par bénéficiaire (P2-6). */
    private function tresorerieDetteFournisseurs(Exercice $ex): array
    {
        $tresorerie = app(\App\Services\Tresorerie\TresorerieService::class);
        $synth = $tresorerie->synthese($ex->id);
        $parFournisseur = $tresorerie->detteParFournisseur($ex->id);
        $detail = $tresorerie->restesAPayer($ex->id)->filter(fn (array $r) => $r['reste'] > 0);

        return [
            [
                'type' => 'kpi',
                'titre' => 'Synthèse dette fournisseurs',
                'items' => [
                    ['label' => 'Reste à payer global (budget)', 'valeur' => $synth['depenses']['reste_a_payer'], 'fmt' => 'money'],
                    ['label' => 'Ordonnancé non soldé (détail)', 'valeur' => $detail->sum('reste'), 'fmt' => 'money'],
                    ['label' => 'Fournisseurs concernés', 'valeur' => $parFournisseur->count(), 'fmt' => 'int'],
                    ['label' => 'Engagements avec reste à payer', 'valeur' => $detail->count(), 'fmt' => 'int'],
                    ['label' => 'Encaissements exercice', 'valeur' => $synth['flux']['encaissements'], 'fmt' => 'money'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Dette par fournisseur / bénéficiaire',
                'colonnes' => [
                    $this->col('Fournisseur / bénéficiaire'),
                    $this->col('Engagements', 'int'),
                    $this->col('Ordonnancé', 'money'),
                    $this->col('Payé', 'money'),
                    $this->col('Reste à payer', 'money'),
                ],
                'lignes' => $parFournisseur->map(fn ($r) => [
                    $r['fournisseur'], $r['engagements'], $r['ordonnance'], $r['paye'], $r['reste'],
                ])->all(),
                'total' => [
                    'TOTAL',
                    $parFournisseur->sum('engagements'),
                    $parFournisseur->sum('ordonnance'),
                    $parFournisseur->sum('paye'),
                    $parFournisseur->sum('reste'),
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'Détail par engagement',
                'colonnes' => [
                    $this->col('N° engagement'),
                    $this->col('Objet'),
                    $this->col('Structure'),
                    $this->col('Bénéficiaire'),
                    $this->col('Ordonnancé', 'money'),
                    $this->col('Payé', 'money'),
                    $this->col('Reste à payer', 'money'),
                ],
                'lignes' => $detail->map(fn ($r) => [
                    $r['numero'], $r['objet'], $r['structure'], $r['beneficiaire'],
                    $r['ordonnance'], $r['paye'], $r['reste'],
                ])->all(),
                'total' => [
                    'TOTAL', '', '', '',
                    $detail->sum('ordonnance'),
                    $detail->sum('paye'),
                    $detail->sum('reste'),
                ],
            ],
        ];
    }

    private function kpiGlobal(array $g): array
    {
        return [
            'type' => 'kpi', 'titre' => 'Synthèse globale',
            'items' => [
                ['label' => 'Crédits alloués', 'valeur' => $g['alloue'] ?? $g['ouvert'], 'fmt' => 'money'],
                ['label' => 'Engagés', 'valeur' => $g['engage'], 'fmt' => 'money'],
                ['label' => 'Liquidés', 'valeur' => $g['liquide'], 'fmt' => 'money'],
                ['label' => 'Ordonnancés', 'valeur' => $g['ordonnance'], 'fmt' => 'money'],
                ['label' => 'Payés', 'valeur' => $g['paye'], 'fmt' => 'money'],
                ['label' => "Taux d'engagement", 'valeur' => $g['taux_engagement'], 'fmt' => 'percent'],
                ['label' => 'Taux de liquidation', 'valeur' => $g['taux_liquidation'] ?? 0, 'fmt' => 'percent'],
                ['label' => "Taux d'ordonnancement", 'valeur' => $g['taux_ordonnancement'] ?? 0, 'fmt' => 'percent'],
                ['label' => 'Taux de paiement', 'valeur' => $g['taux_paiement'] ?? 0, 'fmt' => 'percent'],
                ['label' => "Taux d'exécution", 'valeur' => $g['taux_execution'], 'fmt' => 'percent'],
            ],
        ];
    }

    private function col(string $label, string $fmt = 'text'): array
    {
        return ['label' => $label, 'fmt' => $fmt, 'align' => in_array($fmt, ['money', 'percent', 'num', 'int'], true) ? 'right' : 'left'];
    }

    private function tauxGlobal(Collection $rows): float
    {
        $alloue = $rows->sum(fn ($r) => $r['alloue'] ?? $r['ouvert']);

        return SuiviFinancierCalculator::taux($rows->sum('paye'), $alloue);
    }
}
