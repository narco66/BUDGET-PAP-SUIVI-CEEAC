<?php

namespace Database\Seeders;

use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Strategie\AlignementStrategique;
use App\Models\Strategie\IndicateurStrategique;
use App\Models\Strategie\ObjectifStrategique;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\PortefeuilleProjet;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Models\Strategie\ProgrammationPluriannuelle;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\Strategie\RisqueStrategique;
use App\Models\Strategie\Vision;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Jeu de référence idempotent — Vision 2035, PSC, orientations, programme et projet de démonstration. */
class PlanificationStrategiqueSeeder extends Seeder
{
    public function run(): void
    {
        $responsable = User::query()->where('actif', true)->orderBy('id')->first();

        $vision = Vision::updateOrCreate(
            ['reference' => 'VIS-2035'],
            [
                'intitule' => 'Vision communautaire CEEAC 2035',
                'periode' => '2025-2035',
                'date_debut' => '2025-01-01',
                'date_fin' => '2035-12-31',
                'statut' => 'actif',
                'version' => 1,
                'description' => 'Vision institutionnelle de la Commission de la CEEAC pour une intégration régionale accélérée.',
            ]
        );

        $plan = PlanStrategique::updateOrCreate(
            ['reference' => 'PSC-2025-2030'],
            [
                'intitule' => 'Plan Stratégique Communautaire 2025-2030',
                'vision_id' => $vision->id,
                'date_debut' => '2025-01-01',
                'date_fin' => '2030-12-31',
                'statut' => 'actif',
                'version' => 1,
                'description' => 'Plan stratégique communautaire de la CEEAC — cycle 2025-2030.',
            ]
        );

        $orientations = [
            ['code' => 'OS-01', 'intitule' => 'Intégration économique et monétaire', 'ordre' => 1],
            ['code' => 'OS-02', 'intitule' => 'Paix, sécurité et stabilité', 'ordre' => 2],
            ['code' => 'OS-03', 'intitule' => 'Développement humain et social', 'ordre' => 3],
            ['code' => 'OS-04', 'intitule' => 'Gouvernance et institutions', 'ordre' => 4],
        ];

        foreach ($orientations as $data) {
            OrientationStrategique::updateOrCreate(
                ['plan_strategique_id' => $plan->id, 'code' => $data['code']],
                [
                    'vision_id' => $vision->id,
                    'intitule' => $data['intitule'],
                    'ordre' => $data['ordre'],
                    'statut' => 'actif',
                ]
            );
        }

        $orientation = OrientationStrategique::where('plan_strategique_id', $plan->id)
            ->where('code', 'OS-01')
            ->first();

        $programme = ProgrammeStrategique::updateOrCreate(
            ['orientation_strategique_id' => $orientation->id, 'code' => 'PS-INT-ECO'],
            [
                'intitule' => 'Programme d\'intégration économique régionale',
                'responsable_user_id' => $responsable?->id,
                'date_debut' => '2025-01-01',
                'date_fin' => '2030-12-31',
                'budget_previsionnel' => 15_000_000_000,
                'objectifs' => 'Accélérer la convergence économique des États membres.',
                'resultats_attendus' => 'Zone de libre-échange opérationnelle et harmonisation réglementaire.',
                'statut' => 'actif',
                'version' => 1,
            ]
        );

        if ($responsable) {
            $projet = ProjetStrategique::updateOrCreate(
                ['programme_strategique_id' => $programme->id, 'code' => 'PRJ-ZLECAF'],
                [
                    'intitule' => 'Projet d\'appui à la ZLECAF',
                    'responsable_user_id' => $responsable->id,
                    'statut' => 'en_cours',
                    'cycle_vie' => 'execution',
                    'date_debut' => '2026-01-01',
                    'date_fin' => '2028-12-31',
                    'budget_previsionnel' => 2_500_000_000,
                    'description' => 'Projet phare d\'appui à la mise en œuvre de la ZLECAF.',
                ]
            );

            $portefeuille = PortefeuilleProjet::updateOrCreate(
                ['reference' => 'PF-CEEAC-2026'],
                [
                    'intitule' => 'Portefeuille institutionnel CEEAC 2026',
                    'exercice_id' => Exercice::firstWhere('annee', 2026)?->id,
                    'responsable_user_id' => $responsable->id,
                    'statut' => 'actif',
                ]
            );

            $portefeuille->projets()->syncWithoutDetaching([
                $projet->id => ['priorite' => 1, 'categorie' => 'prioritaire'],
            ]);
        }

        $programmationPluriannuelle = ProgrammationPluriannuelle::updateOrCreate(
            ['reference' => 'PROG-PLURI-2026-2030'],
            [
                'plan_strategique_id' => $plan->id,
                'annee_debut' => 2026,
                'annee_fin' => 2030,
                'statut' => 'actif',
                'version' => 1,
                'description' => 'Programmation pluriannuelle glissante 2026-2030.',
                'scenarios' => [
                    'optimiste' => ['taux_croissance' => 6.5],
                    'realiste' => ['taux_croissance' => 4.0],
                    'pessimiste' => ['taux_croissance' => 2.0],
                ],
            ]
        );

        $exercice2026 = Exercice::firstWhere('annee', 2026);
        if ($exercice2026) {
            $pap2026 = Pap::firstWhere('exercice_id', $exercice2026->id);
            ProgrammationAnnuelle::updateOrCreate(
                [
                    'programmation_pluriannuelle_id' => $programmationPluriannuelle->id,
                    'exercice_id' => $exercice2026->id,
                ],
                [
                    'pap_id' => $pap2026?->id,
                    'statut' => 'actif',
                    'activites_prevues' => 'Activités opérationnelles déclinées du PSC pour 2026.',
                    'ressources_prevues' => 'Crédits CEEAC et cofinancements PTF.',
                    'indicateurs_prevus' => 'Indicateurs stratégiques et opérationnels du PAP 2026.',
                ]
            );
        }

        $objectif = ObjectifStrategique::updateOrCreate(
            ['plan_strategique_id' => $plan->id, 'code' => 'OBJ-INT-01'],
            [
                'orientation_strategique_id' => $orientation->id,
                'intitule' => 'Renforcer l\'intégration économique régionale',
                'ordre' => 1,
                'statut' => 'actif',
            ]
        );

        IndicateurStrategique::updateOrCreate(
            ['code' => 'IND-STR-ZLECAF-01'],
            [
                'objectif_strategique_id' => $objectif->id,
                'libelle' => 'Taux de mise en œuvre des engagements ZLECAF',
                'unite' => '%',
                'periodicite' => 'annuel',
                'valeur_reference' => 45,
                'valeur_cible' => 80,
                'seuil_alerte' => 55,
                'statut' => 'actif',
            ]
        );

        RisqueStrategique::updateOrCreate(
            ['plan_strategique_id' => $plan->id, 'code' => 'RSK-INT-01'],
            [
                'description' => 'Retard dans l\'harmonisation réglementaire entre États membres.',
                'probabilite' => 'moyenne',
                'impact' => 'eleve',
                'mitigation' => 'Renforcer le dialogue politique et les comités techniques sectoriels.',
                'responsable_user_id' => $responsable?->id,
                'statut' => 'identifie',
            ]
        );

        AlignementStrategique::updateOrCreate(
            [
                'plan_strategique_id' => $plan->id,
                'alignable_type' => ProgrammeStrategique::class,
                'alignable_id' => $programme->id,
            ],
            [
                'vision_id' => $vision->id,
                'orientation_strategique_id' => $orientation->id,
                'niveau_alignement' => 'direct',
                'commentaire' => 'Alignement direct programme stratégique sur orientation OS-01.',
            ]
        );
    }
}
