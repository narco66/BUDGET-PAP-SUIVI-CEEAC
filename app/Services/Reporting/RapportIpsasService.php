<?php

namespace App\Services\Reporting;

use App\Models\Exercice;
use App\Models\Immobilisation;
use App\Models\Rapport;
use App\Models\RapportIpsas;
use App\Services\Recettes\GestionRecettesService;
use App\Services\Recettes\RecettesReportingService;
use Illuminate\Support\Facades\Schema;

/**
 * Données préparatoires IPSAS — bilan, compte de résultat et annexes SYSCOHADA/IPSAS (P3-10).
 * Les montants proviennent des mêmes sources que le reporting budgétaire institutionnel.
 */
class RapportIpsasService
{
    public function __construct(
        private readonly ReportingService $reporting,
        private readonly RecettesReportingService $recettes,
        private readonly GestionRecettesService $gestionRecettes,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function sections(Exercice $exercice): array
    {
        $donnees = $this->donneesPreparatoires($exercice);
        $global = $donnees['budget'];
        $recettes = $donnees['recettes'];
        $mobilisation = $donnees['mobilisation'];
        $bilan = $donnees['bilan'];
        $resultat = $donnees['compte_resultat'];

        $colBilan = [
            ['label' => 'Poste', 'fmt' => 'text', 'align' => 'left'],
            ['label' => 'Montant (XAF)', 'fmt' => 'money', 'align' => 'right'],
        ];

        return [
            [
                'type' => 'text',
                'titre' => 'Note de présentation IPSAS',
                'contenu' => "État financier préparatoire conforme aux principes IPSAS (base d'engagement) — exercice {$exercice->annee}. "
                    .'Inclut le bilan, le compte de résultat et les annexes d\'exécution budgétaire. '
                    .'Les données sont extraites automatiquement de la chaîne budgétaire CEEAC (lignes, engagements, recettes, contributions, immobilisations). '
                    .'Ce document constitue une base de travail pour la consolidation comptable officielle.',
            ],
            [
                'type' => 'kpi',
                'titre' => 'Situation financière — synthèse IPSAS',
                'items' => [
                    ['label' => 'Total actif (bilan)', 'valeur' => $bilan['total_actif'], 'fmt' => 'money'],
                    ['label' => 'Total passif (bilan)', 'valeur' => $bilan['total_passif'], 'fmt' => 'money'],
                    ['label' => 'Résultat net (exercice)', 'valeur' => $resultat['resultat_net'], 'fmt' => 'money'],
                    ['label' => 'Crédits alloués', 'valeur' => $global['alloue'] ?? $global['ouvert'], 'fmt' => 'money'],
                    ['label' => 'Dépenses payées', 'valeur' => $global['paye'], 'fmt' => 'money'],
                    ['label' => 'Restes à payer', 'valeur' => $global['reste_a_payer'], 'fmt' => 'money'],
                    ['label' => 'Recettes réalisées', 'valeur' => $recettes['montant_realise'], 'fmt' => 'money'],
                    ['label' => 'Arriérés contributions', 'valeur' => $mobilisation['arriere'], 'fmt' => 'money'],
                ],
            ],
            [
                'type' => 'table',
                'titre' => 'État 1 — Bilan (Actif)',
                'colonnes' => $colBilan,
                'lignes' => collect($bilan['actif'])->map(fn ($r) => [$r['libelle'], $r['montant']])->all(),
                'total' => ['TOTAL ACTIF', $bilan['total_actif']],
            ],
            [
                'type' => 'table',
                'titre' => 'État 2 — Bilan (Passif et fonds propres)',
                'colonnes' => $colBilan,
                'lignes' => collect($bilan['passif'])->map(fn ($r) => [$r['libelle'], $r['montant']])->all(),
                'total' => ['TOTAL PASSIF', $bilan['total_passif']],
            ],
            [
                'type' => 'table',
                'titre' => 'État 3 — Compte de résultat',
                'colonnes' => $colBilan,
                'lignes' => collect($resultat['lignes'])->map(fn ($r) => [$r['libelle'], $r['montant']])->all(),
                'total' => ['RÉSULTAT NET', $resultat['resultat_net']],
            ],
            [
                'type' => 'table',
                'titre' => 'Annexe 1 — Exécution par nature budgétaire (SYSCOHADA / Titres)',
                'colonnes' => [
                    ['label' => 'Code', 'fmt' => 'text', 'align' => 'left'],
                    ['label' => 'Nature', 'fmt' => 'text', 'align' => 'left'],
                    ['label' => 'Alloué', 'fmt' => 'money', 'align' => 'right'],
                    ['label' => 'Engagé', 'fmt' => 'money', 'align' => 'right'],
                    ['label' => 'Payé', 'fmt' => 'money', 'align' => 'right'],
                    ['label' => 'Taux exéc.', 'fmt' => 'percent', 'align' => 'right'],
                ],
                'lignes' => collect($donnees['natures'])->map(fn ($r) => [
                    $r['code'], $r['intitule'], $r['alloue'] ?? $r['ouvert'], $r['engage'], $r['paye'], $r['taux_execution'],
                ])->all(),
            ],
            [
                'type' => 'table',
                'titre' => 'Annexe 2 — Exécution par source de financement (passif / ressources)',
                'colonnes' => [
                    ['label' => 'Source', 'fmt' => 'text', 'align' => 'left'],
                    ['label' => 'Bailleur', 'fmt' => 'text', 'align' => 'left'],
                    ['label' => 'Alloué', 'fmt' => 'money', 'align' => 'right'],
                    ['label' => 'Engagé', 'fmt' => 'money', 'align' => 'right'],
                    ['label' => 'Payé', 'fmt' => 'money', 'align' => 'right'],
                    ['label' => 'Taux exéc.', 'fmt' => 'percent', 'align' => 'right'],
                ],
                'lignes' => collect($donnees['bailleurs'])->map(fn ($r) => [
                    $r['libelle'], $r['bailleur'] ?? '—', $r['ouvert'], $r['engage'], $r['paye'], $r['taux_execution'],
                ])->all(),
            ],
            [
                'type' => 'table',
                'titre' => 'Annexe 3 — Mobilisation des contributions (États membres)',
                'colonnes' => [
                    ['label' => 'État', 'fmt' => 'text', 'align' => 'left'],
                    ['label' => 'Appelé', 'fmt' => 'money', 'align' => 'right'],
                    ['label' => 'Recouvré', 'fmt' => 'money', 'align' => 'right'],
                    ['label' => 'Arriéré', 'fmt' => 'money', 'align' => 'right'],
                    ['label' => 'Taux', 'fmt' => 'percent', 'align' => 'right'],
                ],
                'lignes' => collect($donnees['mobilisation_etats'])->map(fn ($r) => [
                    $r['pays'], $r['appele'], $r['recouvre'], $r['arriere'], $r['taux'],
                ])->all(),
                'total' => [
                    'TOTAL',
                    $mobilisation['appele'],
                    $mobilisation['recouvre'],
                    $mobilisation['arriere'],
                    $mobilisation['taux'],
                ],
            ],
            [
                'type' => 'kpi',
                'titre' => 'Annexe 4 — Indicateurs de conformité et passifs',
                'items' => [
                    ['label' => "Taux d'exécution budgétaire", 'valeur' => $global['taux_execution'], 'fmt' => 'percent'],
                    ['label' => 'Restes à engager', 'valeur' => $global['reste_a_engager'], 'fmt' => 'money'],
                    ['label' => 'Taux recouvrement contributions', 'valeur' => $mobilisation['taux'], 'fmt' => 'percent'],
                    ['label' => 'Nombre de sources de financement', 'valeur' => count($donnees['bailleurs']), 'fmt' => 'int'],
                    ['label' => 'Nombre de natures budgétaires', 'valeur' => count($donnees['natures']), 'fmt' => 'int'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function donneesPreparatoires(Exercice $exercice): array
    {
        $global = $this->reporting->global($exercice->id);
        $bailleur = $this->reporting->parBailleur($exercice->id);
        $natures = $this->reporting->parCategorie($exercice->id)->values()->all();
        $recettes = $this->gestionRecettes->syntheseExercice($exercice->id);
        $mobilisation = $this->recettes->syntheseMobilisation($exercice->id);
        $mobilisationEtats = $this->recettes->mobilisationParEtat($exercice->id)
            ->map(fn ($r) => [
                'pays' => $r->pays,
                'code' => $r->code,
                'appele' => $r->appele,
                'recouvre' => $r->recouvre,
                'arriere' => $r->arriere,
                'taux' => $r->taux,
            ])->all();

        $base = [
            'exercice' => $exercice->annee,
            'genere_le' => now()->toIso8601String(),
            'reference_note' => 'IPSAS-COMPLET',
            'budget' => $global,
            'recettes' => $recettes,
            'mobilisation' => $mobilisation,
            'mobilisation_etats' => $mobilisationEtats,
            'natures' => $natures,
            'bailleurs' => $bailleur['sources']->values()->all(),
            'ceeac_ptf' => $bailleur['ceeac_ptf']->values()->all(),
        ];

        $bilan = $this->construireBilan($base, $exercice->id);
        $compteResultat = $this->construireCompteResultat($base);

        return $base + [
            'bilan' => $bilan,
            'compte_resultat' => $compteResultat,
            'immobilisations_vnc' => $this->totalImmobilisations($exercice->id),
        ];
    }

    /** @return array{actif: list<array{libelle: string, montant: float}>, passif: list<array{libelle: string, montant: float}>, total_actif: float, total_passif: float} */
    public function construireBilan(array $donnees, int $exerciceId): array
    {
        $global = $donnees['budget'];
        $mobilisation = $donnees['mobilisation'];
        $recettes = $donnees['recettes'];

        $immobilisations = $this->totalImmobilisations($exerciceId);
        $creancesEtats = (float) ($mobilisation['arriere'] ?? 0);
        $produitsEncaisses = (float) ($recettes['montant_realise'] ?? 0) + (float) ($mobilisation['recouvre'] ?? 0);
        $depensesPayees = (float) ($global['paye'] ?? 0);
        $tresorerie = max(0, round($produitsEncaisses - $depensesPayees, 2));

        $actif = [
            ['libelle' => 'Immobilisations corporelles et incorporelles (VNC)', 'montant' => $immobilisations],
            ['libelle' => 'Créances sur les états membres (contributions)', 'montant' => $creancesEtats],
            ['libelle' => 'Trésorerie et équivalents (estimation)', 'montant' => $tresorerie],
        ];
        $totalActif = round(array_sum(array_column($actif, 'montant')), 2);

        $dettesFournisseurs = (float) ($global['reste_a_payer'] ?? 0);
        $fondsPropres = round($totalActif - $dettesFournisseurs, 2);

        $passif = [
            ['libelle' => 'Dettes fournisseurs et charges à payer', 'montant' => $dettesFournisseurs],
            [
                'libelle' => $fondsPropres >= 0
                    ? 'Fonds propres / excédent d\'exploitation'
                    : 'Déficit cumulé (capitaux propres négatifs)',
                'montant' => abs($fondsPropres),
            ],
        ];
        $totalPassif = $totalActif;

        return compact('actif', 'passif') + ['total_actif' => $totalActif, 'total_passif' => $totalPassif];
    }

    /** @return array{lignes: list<array{libelle: string, montant: float}>, resultat_net: float} */
    public function construireCompteResultat(array $donnees): array
    {
        $global = $donnees['budget'];
        $recettes = $donnees['recettes'];
        $mobilisation = $donnees['mobilisation'];

        $produitsRecettes = (float) ($recettes['montant_realise'] ?? 0);
        $produitsContributions = (float) ($mobilisation['recouvre'] ?? 0);
        $totalProduits = round($produitsRecettes + $produitsContributions, 2);

        $chargesPayees = (float) ($global['paye'] ?? 0);
        $chargesEngagees = (float) ($global['engage'] ?? 0);
        $resultatNet = round($totalProduits - $chargesPayees, 2);

        $lignes = [
            ['libelle' => 'Produits — recettes ordinaires', 'montant' => $produitsRecettes],
            ['libelle' => 'Produits — contributions des états membres', 'montant' => $produitsContributions],
            ['libelle' => 'Total des produits', 'montant' => $totalProduits],
            ['libelle' => 'Charges — dépenses payées (base trésorerie)', 'montant' => $chargesPayees],
            ['libelle' => 'Charges — dépenses engagées (base d\'engagement)', 'montant' => $chargesEngagees],
        ];

        return ['lignes' => $lignes, 'resultat_net' => $resultatNet];
    }

    private function totalImmobilisations(int $exerciceId): float
    {
        if (! Schema::hasTable('immobilisations')) {
            return 0.0;
        }

        return round(
            Immobilisation::query()
                ->where('exercice_id', $exerciceId)
                ->whereIn('statut', ['en_service', 'en_maintenance'])
                ->get()
                ->sum(fn (Immobilisation $i) => $i->valeurNette()),
            2
        );
    }

    public function enregistrerSnapshot(Rapport $rapport, Exercice $exercice): RapportIpsas
    {
        return RapportIpsas::updateOrCreate(
            ['rapport_id' => $rapport->id],
            [
                'exercice_id' => $exercice->id,
                'reference_note' => 'IPSAS-COMPLET',
                'donnees_preparatoires' => $this->donneesPreparatoires($exercice),
            ]
        );
    }
}
