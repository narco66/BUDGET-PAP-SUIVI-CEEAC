<?php

namespace Database\Seeders;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Reprise du PAP 2026 : 6 piliers et leurs axes (états « PAP par pilier » du budget validé).
 * Les montants des états PAP sont exprimés en MILLIERS de XAF → conversion ×1000.
 * Le budget de chaque axe est porté par une activité de synthèse (le détail des activités
 * sera saisi ultérieurement). Total PAP attendu : 22 365 281 000 XAF (CEEAC 8 335 000 000 + PTF 14 030 281 000).
 */
class Pap2026Seeder extends Seeder
{
    private const TOTAL_ATTENDU = 22_365_281_000;
    private const K = 1000;

    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            throw new RuntimeException('Exercice 2026 absent.');
        }

        // [code pilier, intitulé, axes[ [code, intitulé, ceeac(k), ptf(k)] ]]
        $piliers = [
            ['201', 'Intégration politique, paix et sécurité', [
                ['2011', 'Renforcer la gouvernance politique', 655_000, 0],
                ['2012', 'Maintenir, rétablir et consolider la paix en Afrique centrale', 885_000, 0],
                ['2013', 'Prévenir les conflits et crises en Afrique centrale', 210_000, 0],
            ]],
            ['202', 'Intégration économique et commerciale', [
                ['2021', 'Opérationnaliser le marché commun régional', 625_000, 265_000],
                ['2022', "Améliorer la gouvernance économique et l'environnement des affaires", 275_000, 0],
                ['2023', "Renforcer les capacités productives, d'innovation et l'écosystème des entreprises", 510_000, 980_000],
                ['2024', 'Opérationnaliser le système statistique de la Communauté', 340_000, 100_000],
            ]],
            ['203', 'Aménagement du territoire, infrastructures et intégration physique', [
                ['2031', 'Développer des infrastructures de transport de qualité et accessibles', 290_000, 6_638_536],
                ['2032', "Accélérer la mise en place d'un marché régional de l'énergie", 500_000, 2_642_525],
                ['2033', "Accélérer l'intégration régionale numérique", 210_000, 891_000],
            ]],
            ['204', 'Intégration environnementale, agriculture et transformation productive', [
                ['2041', 'Agriculture, développement rural et sécurité alimentaire et nutritionnelle', 340_000, 515_020],
                ['2042', 'Environnement, ressources naturelles, biodiversité et changement climatique', 660_000, 1_392_100],
            ]],
            ['205', 'Promotion du genre, intégration humaine et sociale', [
                ['2051', 'Renforcer les systèmes communautaires de santé et de protection sociale', 420_000, 606_100],
                ['2052', "Faciliter l'inclusion socio-économique des jeunes, le sport et l'emploi", 150_000, 0],
                ['2053', "Intégration systématique du genre et autonomisation des femmes", 110_000, 0],
                ['2054', 'Transformer les systèmes éducatifs, scientifiques, technologiques et culturels', 520_000, 0],
                ['2055', 'Renforcer les cadres de gestion des migrations, des réfugiés et de l\'asile', 150_000, 0],
            ]],
            ['206', 'Poursuite de la réforme et renforcement de la gouvernance institutionnelle', [
                ['2061', "Réviser les textes fondamentaux et adopter les textes d'application", 135_000, 0],
                ['2062', 'Améliorer les capacités opérationnelles de la CEEAC', 362_000, 0],
                ['2063', 'Renforcer les mécanismes de communication, planification et contrôle', 792_000, 0],
                ['2064', 'Coordination et renforcement des cadres de coopération et de mobilisation des ressources', 196_000, 0],
            ]],
        ];

        $total = 0;
        foreach ($piliers as $pi => [$pCode, $pLib, $axes]) {
            $pilier = Pilier::updateOrCreate(
                ['exercice_id' => $exercice->id, 'code' => $pCode],
                ['intitule' => $pLib, 'ordre' => $pi + 1]
            );

            foreach ($axes as $ai => [$aCode, $aLib, $ceeacK, $ptfK]) {
                $axe = Axe::updateOrCreate(
                    ['pilier_id' => $pilier->id, 'code' => $aCode],
                    ['intitule' => $aLib, 'ordre' => $ai + 1]
                );

                // Chaîne de synthèse Produit → Sous-Produit → Activité portant le budget de l'axe
                $produit = Produit::updateOrCreate(
                    ['axe_id' => $axe->id, 'code' => 'PR-'.$aCode],
                    ['intitule' => 'Programmes — '.$aLib, 'ordre' => 1]
                );
                $sp = SousProduit::updateOrCreate(
                    ['produit_id' => $produit->id, 'code' => 'SP-'.$aCode],
                    ['intitule' => 'Composante synthèse', 'ordre' => 1]
                );

                $ceeac = $ceeacK * self::K;
                $ptf = $ptfK * self::K;
                Activite::updateOrCreate(
                    ['sous_produit_id' => $sp->id, 'code' => 'ACT-'.$aCode],
                    [
                        'produit_id' => $produit->id,
                        'intitule' => 'Enveloppe des programmes de l\'axe '.$aCode,
                        'montant_ceeac' => $ceeac,
                        'montant_ptf' => $ptf,
                        'montant_total' => $ceeac + $ptf,
                        'trim_1' => true, 'trim_2' => true, 'trim_3' => true, 'trim_4' => true,
                        'ordre' => 1,
                    ]
                );
                $total += $ceeac + $ptf;
            }
        }

        if ($total !== self::TOTAL_ATTENDU) {
            throw new RuntimeException(sprintf(
                'Contrôle PAP échoué : total %s ≠ attendu %s.',
                number_format($total, 0, ',', ' '), number_format(self::TOTAL_ATTENDU, 0, ',', ' ')
            ));
        }
    }
}
