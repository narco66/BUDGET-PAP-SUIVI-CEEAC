<?php

namespace Database\Seeders\Demo;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Budget;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\LigneBudgetaire;
use App\Models\Mesure;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SourceFinancement;
use App\Models\SourceVerification;
use App\Models\Structure;
use App\Models\Tache;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu demo institutionnel complet, idempotent et non destructif.
 *
 * Objectif : obtenir une base exploitable en demonstration officielle sans
 * donnees absurdes, sans doublons de codes et avec une chaine RBM/PAP/Budget
 * assez complete pour alimenter les tableaux de bord.
 */
class InstitutionalShowcaseSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** @var array<int, array<string, mixed>> */
    private array $departements = [
        ['code' => 'APPS', 'sigle' => 'APPS', 'libelle' => 'Affaires politiques, paix et securite'],
        ['code' => 'MCAEMF', 'sigle' => 'MCAEMF', 'libelle' => 'Marche commun, affaires economiques, monetaires et financieres'],
        ['code' => 'ERNADR', 'sigle' => 'ERNADR', 'libelle' => 'Environnement, ressources naturelles, agriculture et developpement rural'],
        ['code' => 'ATI', 'sigle' => 'ATI', 'libelle' => 'Amenagement du territoire et infrastructures'],
        ['code' => 'PGDHS', 'sigle' => 'PGDHS', 'libelle' => 'Promotion du genre, developpement humain et social'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->exercices();
            $this->sourcesFinancement();
            $this->organisation();
            $this->utilisateurs();
            $this->rbmPapBudgetEtIndicateurs();
        });
    }

    private function exercices(): void
    {
        foreach ([2024, 2025, 2026, 2027] as $annee) {
            Exercice::updateOrCreate(
                ['annee' => $annee],
                [
                    'code' => 'EX-'.$annee,
                    'libelle' => 'Exercice budgetaire '.$annee,
                    'statut' => $annee < 2026 ? 'clos' : 'ouvert',
                    'est_actif' => $annee === 2026,
                    'date_debut' => $annee.'-01-01',
                    'date_fin' => $annee.'-12-31',
                    'date_cloture' => $annee < 2026 ? $annee.'-12-31 18:00:00' : null,
                    'devise' => 'XAF',
                    'taux_change_usd' => 610.000000,
                ]
            );
        }
    }

    private function sourcesFinancement(): void
    {
        foreach ([
            ['CEEAC-CCI', 'Contribution communautaire d integration', 'interne_ceeac', 'fonds_communautaires', null],
            ['UE-PAIRIAC', 'Union europeenne - Appui a l integration regionale', 'externe_ptf', 'cooperation', 'Union europeenne'],
            ['BAD-GOUV', 'Banque africaine de developpement - Gouvernance regionale', 'externe_ptf', 'projet_finance', 'Banque africaine de developpement'],
            ['BM-DIGITAL', 'Banque mondiale - Transformation numerique', 'externe_ptf', 'projet_finance', 'Banque mondiale'],
        ] as [$code, $libelle, $type, $classification, $bailleur]) {
            SourceFinancement::updateOrCreate(
                ['code' => $code],
                [
                    'libelle' => $libelle,
                    'type' => $type,
                    'classification' => $classification,
                    'bailleur' => $bailleur,
                    'actif' => true,
                    'description' => 'Source de financement de demonstration institutionnelle CEEAC.',
                ]
            );
        }
    }

    private function organisation(): void
    {
        $presidence = $this->structure('PRES', 'Presidence de la Commission', 'PRES', 'presidence', null, 1, 1);
        $vicePresidence = $this->structure('VPRES', 'Vice-presidence de la Commission', 'VPRES', 'vice_presidence', null, 1, 2);
        $sg = $this->structure('SG', 'Secretariat general', 'SG', 'secretariat_general', null, 1, 3);

        $this->structure('CAB-PRES', 'Cabinet du President', 'CAB-PRES', 'cellule', $presidence->id, 2, 1);
        $this->structure('BCJ', 'Bureau du Conseiller Juridique', 'BCJ', 'service', $presidence->id, 2, 2);
        $this->structure('CAB-VPRES', 'Cabinet de la Vice-presidence', 'CAB-VPRES', 'cellule', $vicePresidence->id, 2, 1);
        $this->structure('DSG', 'Direction de la coordination administrative', 'DSG', 'direction', $sg->id, 2, 1);
        $this->structure('DIR-BUDGET', 'Direction du budget et des finances', 'DBF', 'direction', $sg->id, 2, 2);
        $this->structure('SVC-EXEC', 'Service execution budgetaire', 'SEB', 'service', Structure::where('code', 'DIR-BUDGET')->value('id'), 3, 1);

        foreach ($this->departements as $ordre => $departement) {
            $dep = $this->structure(
                $departement['code'],
                'Departement '.$departement['libelle'],
                $departement['sigle'],
                'departement',
                $vicePresidence->id,
                2,
                $ordre + 1
            );

            foreach ([1 => 'Programmation et suivi', 2 => 'Operations et partenariats'] as $rang => $direction) {
                $dir = $this->structure(
                    'DIR-'.$departement['code'].'-'.$rang,
                    'Direction '.$direction.' - '.$departement['sigle'],
                    'D'.$rang.'-'.$departement['sigle'],
                    'direction',
                    $dep->id,
                    3,
                    $rang
                );

                foreach ([1 => 'Planification', 2 => 'Suivi et reporting'] as $serviceRang => $service) {
                    $this->structure(
                        'SVC-'.$departement['code'].'-'.$rang.$serviceRang,
                        'Service '.$service.' - '.$departement['sigle'],
                        'S'.$serviceRang.'-'.$departement['sigle'],
                        'service',
                        $dir->id,
                        4,
                        $serviceRang
                    );
                }
            }
        }
    }

    private function utilisateurs(): void
    {
        $users = [
            ['president', 'President de la Commission', 'president@ceeac.demo', 'PRES', 'president'],
            ['vice_presidente', 'Vice-presidente chargee de l integration', 'vice.presidence@ceeac.demo', 'VPRES', 'vice_president'],
            ['secretaire_general', 'Secretaire general de la Commission', 'secretariat.general@ceeac.demo', 'SG', 'secretaire_general'],
            ['directrice_budget', 'Directrice du budget et des finances', 'directrice.budget@ceeac.demo', 'DIR-BUDGET', 'directeur'],
            ['chef_execution', 'Chef du service execution budgetaire', 'chef.execution@ceeac.demo', 'SVC-EXEC', 'chef_service'],
            ['gestionnaire_budget', 'Gestionnaire budgetaire institutionnel', 'gestionnaire.budget@ceeac.demo', 'SVC-EXEC', 'gestionnaire_budgetaire'],
            ['controleur_financier', 'Controleur financier principal', 'controleur.financier@ceeac.demo', 'DIR-BUDGET', 'controleur_financier'],
            ['comptable', 'Comptable public principal', 'comptable@ceeac.demo', 'DIR-BUDGET', 'comptable'],
            ['auditeur', 'Auditeur interne senior', 'auditeur.interne@ceeac.demo', 'SG', 'auditeur'],
        ];

        foreach ($this->departements as $departement) {
            $users[] = ['commissaire_'.strtolower($departement['code']), 'Commissaire '.$departement['sigle'], 'commissaire.'.strtolower($departement['code']).'@ceeac.demo', $departement['code'], 'commissaire'];
            $users[] = ['directeur_'.strtolower($departement['code']), 'Directeur programmes '.$departement['sigle'], 'directeur.'.strtolower($departement['code']).'@ceeac.demo', 'DIR-'.$departement['code'].'-1', 'directeur'];
            $users[] = ['chef_service_'.strtolower($departement['code']), 'Chef service suivi '.$departement['sigle'], 'chef.suivi.'.strtolower($departement['code']).'@ceeac.demo', 'SVC-'.$departement['code'].'-12', 'chef_service'];
            $users[] = ['expert_'.strtolower($departement['code']), 'Expert sectoriel '.$departement['sigle'], 'expert.'.strtolower($departement['code']).'@ceeac.demo', 'SVC-'.$departement['code'].'-11', 'point_focal'];
        }

        foreach ($users as [$matricule, $name, $email, $structureCode, $role]) {
            $structureId = Structure::where('code', $structureCode)->value('id');
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'matricule' => 'CEEAC-'.strtoupper(str_replace('_', '-', $matricule)),
                    'password' => Hash::make(self::PASSWORD),
                    'structure_id' => $structureId,
                    'actif' => true,
                    'statut' => 'actif',
                    'doit_changer_mot_de_passe' => false,
                ]
            );

            if ($role && ! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            if ($structureId) {
                $user->structures()->syncWithoutDetaching([$structureId => ['principal' => true]]);
                Structure::whereKey($structureId)->whereNull('responsable_user_id')->update(['responsable_user_id' => $user->id]);
            }
        }
    }

    private function rbmPapBudgetEtIndicateurs(): void
    {
        $rubriques = BudgetRubrique::query()->orderBy('code')->limit(8)->get();
        $sourceId = SourceFinancement::where('code', 'CEEAC-CCI')->value('id');
        $createurId = User::where('email', 'gestionnaire.budget@ceeac.demo')->value('id') ?? User::query()->value('id');
        $typeId = TypeIndicateur::query()->value('id');
        $uniteId = UniteMesure::query()->value('id');
        $sourceVerification = SourceVerification::firstOrCreate(
            ['code' => 'RPT-PERF-CEEAC'],
            ['libelle' => 'Rapports periodiques de performance CEEAC', 'statut' => 'actif', 'ordre' => 1]
        );

        foreach (Exercice::whereIn('annee', [2024, 2025, 2026, 2027])->orderBy('annee')->get() as $exercice) {
            $pap = Pap::updateOrCreate(
                ['exercice_id' => $exercice->id],
                [
                    'reference' => 'PAP-'.$exercice->annee.'-CEEAC',
                    'libelle' => 'Plan annuel de performance CEEAC '.$exercice->annee,
                    'statut' => $exercice->annee < 2026 ? 'publie' : 'brouillon',
                    'date_elaboration' => $exercice->annee.'-01-15',
                    'cree_par' => $createurId,
                ]
            );

            $budget = Budget::updateOrCreate(
                ['exercice_id' => $exercice->id],
                [
                    'reference' => 'BUD-'.$exercice->annee.'-CEEAC',
                    'libelle' => 'Budget institutionnel CEEAC '.$exercice->annee,
                    'statut' => $exercice->annee < 2026 ? 'adopte' : 'brouillon',
                    'date_elaboration' => $exercice->annee.'-01-10',
                    'cree_par' => $createurId,
                    'montant_total' => 0,
                ]
            );

            $totalBudget = 0;

            foreach (array_slice($this->departements, 0, 3) as $depIndex => $departement) {
                $depId = Structure::where('code', $departement['code'])->value('id');
                $pilier = Pilier::updateOrCreate(
                    ['exercice_id' => $exercice->id, 'code' => 'P'.$exercice->annee.'-'.($depIndex + 1)],
                    [
                        'pap_id' => $pap->id,
                        'intitule' => 'Pilier '.$departement['sigle'].' - performance institutionnelle',
                        'description' => 'Contribution du departement '.$departement['sigle'].' au PAP '.$exercice->annee.'.',
                        'ordre' => $depIndex + 1,
                        'statut' => 'actif',
                        'created_by' => $createurId,
                        'updated_by' => $createurId,
                    ]
                );

                $axe = Axe::updateOrCreate(
                    ['pilier_id' => $pilier->id, 'code' => 'A'.$exercice->annee.'-'.($depIndex + 1)],
                    [
                        'intitule' => 'Renforcement des capacites de pilotage '.$departement['sigle'],
                        'objectif_strategique' => 'Ameliorer la planification, l execution et le suivi sectoriel.',
                        'ordre' => 1,
                        'statut' => 'actif',
                        'created_by' => $createurId,
                        'updated_by' => $createurId,
                    ]
                );

                $produit = Produit::updateOrCreate(
                    ['axe_id' => $axe->id, 'code' => 'PR'.$exercice->annee.'-'.($depIndex + 1)],
                    [
                        'structure_id' => $depId,
                        'intitule' => 'Programme sectoriel '.$departement['sigle'].' operationnel',
                        'description' => 'Produit de demonstration reliant strategie, budget et execution.',
                        'ordre' => 1,
                        'statut' => 'actif',
                        'created_by' => $createurId,
                        'updated_by' => $createurId,
                    ]
                );

                $sousProduit = \App\Models\SousProduit::updateOrCreate(
                    ['produit_id' => $produit->id, 'code' => 'SP'.$exercice->annee.'-'.($depIndex + 1)],
                    [
                        'intitule' => 'Outils et livrables '.$departement['sigle'].' disponibles',
                        'description' => 'Sous-produit complet pour les demonstrations RBM/GAR.',
                        'ordre' => 1,
                        'statut' => 'actif',
                        'created_by' => $createurId,
                        'updated_by' => $createurId,
                    ]
                );

                foreach ([1, 2] as $activityIndex) {
                    $amount = (float) (80_000_000 + ($exercice->annee - 2024) * 12_000_000 + $depIndex * 9_000_000 + $activityIndex * 5_000_000);
                    $activityCode = 'ACT-'.$exercice->annee.'-'.$departement['sigle'].'-'.$activityIndex;
                    $activity = Activite::updateOrCreate(
                        ['code' => $activityCode],
                        [
                            'sous_produit_id' => $sousProduit->id,
                            'produit_id' => $produit->id,
                            'structure_id' => $depId,
                            'responsable_user_id' => User::where('email', 'directeur.'.strtolower($departement['code']).'@ceeac.demo')->value('id'),
                            'intitule' => $activityIndex === 1
                                ? 'Planification et coordination '.$departement['sigle']
                                : 'Suivi, evaluation et reporting '.$departement['sigle'],
                            'description' => 'Activite PAP credibilisee pour demonstration institutionnelle.',
                            'montant_total' => $amount,
                            'montant_ceeac' => round($amount * 0.72, 2),
                            'montant_ptf' => round($amount * 0.28, 2),
                            'trim_1' => true,
                            'trim_2' => true,
                            'trim_3' => $activityIndex === 2,
                            'trim_4' => true,
                            'taux_realisation_physique' => $this->taux($exercice->annee, $activityIndex),
                            'taux_realisation_financier' => $this->taux($exercice->annee, $activityIndex) - 5,
                            'statut' => 'actif',
                            'ordre' => $activityIndex,
                            'created_by' => $createurId,
                            'updated_by' => $createurId,
                        ]
                    );

                    $rubrique = $rubriques->get(($depIndex + $activityIndex) % max(1, $rubriques->count()));
                    if ($rubrique) {
                        $ligneCode = 'LB-'.$activityCode;
                        $existingLine = LigneBudgetaire::where('code', $ligneCode)->first();
                        if ($existingLine) {
                            $engage = min((float) $existingLine->montant_engage, $amount);
                            $liquide = min((float) $existingLine->montant_liquide, $engage);
                            $ordonnance = min((float) $existingLine->montant_ordonnance, $liquide);
                            $paye = min((float) $existingLine->montant_paye, $ordonnance);
                        } else {
                            $engage = round($amount * ($exercice->annee <= 2025 ? 0.86 : 0.58), 2);
                            $liquide = round($engage * ($exercice->annee <= 2025 ? 0.9 : 0.72), 2);
                            $ordonnance = round($liquide * ($exercice->annee <= 2025 ? 0.91 : 0.76), 2);
                            $paye = round($ordonnance * ($exercice->annee <= 2025 ? 0.92 : 0.65), 2);
                        }
                        $ligne = LigneBudgetaire::updateOrCreate(
                            ['code' => $ligneCode],
                            [
                                'exercice_id' => $exercice->id,
                                'budget_id' => $budget->id,
                                'structure_id' => $depId,
                                'rubrique_id' => $rubrique->id,
                                'source_financement_id' => $sourceId,
                                'activite_id' => $activity->id,
                                'intitule' => 'Credit '.$activity->intitule,
                                'nature' => 'depense',
                                'date_entree_vigueur' => $exercice->annee.'-01-01',
                                'montant_ouvert' => $amount,
                                'montant_demande' => round($amount * 1.08, 2),
                                'montant_propose' => $amount,
                                'montant_revise' => $amount,
                                'montant_engage' => $engage,
                                'montant_liquide' => $liquide,
                                'montant_ordonnance' => $ordonnance,
                                'montant_paye' => $paye,
                                'actif' => true,
                            ]
                        );
                        $activity->update(['ligne_budgetaire_id' => $ligne->id]);
                        $totalBudget += (float) ($ligne->montant_revise ?? $ligne->montant_ouvert);
                    }

                    foreach ([1, 2] as $taskIndex) {
                        Tache::updateOrCreate(
                            ['activite_id' => $activity->id, 'code' => 'T-'.$activityCode.'-'.$taskIndex],
                            [
                                'responsable_user_id' => $activity->responsable_user_id,
                                'intitule' => $taskIndex === 1 ? 'Cadrage technique et plan de travail' : 'Execution, controle qualite et rapportage',
                                'date_debut' => $exercice->annee.'-0'.$taskIndex.'-01',
                                'date_fin' => $exercice->annee.'-'.($taskIndex + 4).'-28',
                                'montant' => round($amount / 2, 2),
                                'taux_realisation' => $this->taux($exercice->annee, $taskIndex),
                                'statut' => $exercice->annee <= 2025 ? 'termine' : 'en_cours',
                                'ordre' => $taskIndex,
                                'statut_referentiel' => 'actif',
                                'created_by' => $createurId,
                                'updated_by' => $createurId,
                            ]
                        );
                    }

                    $indicator = Indicateur::updateOrCreate(
                        ['code' => 'IND-'.$activityCode],
                        [
                            'rbm_id' => $activity->id,
                            'rbm_type' => Activite::class,
                            'libelle' => 'Taux de realisation de '.$activity->intitule,
                            'type' => 'operationnel',
                            'type_indicateur_id' => $typeId,
                            'unite' => '%',
                            'unite_mesure_id' => $uniteId,
                            'methode_calcul' => 'Realise / cible x 100',
                            'source_verification' => 'Rapports trimestriels et PV de validation',
                            'periodicite' => 'trimestriel',
                            'valeur_reference' => 0,
                            'valeur_cible' => 100,
                            'seuil_alerte' => 60,
                            'statut' => 'actif',
                            'created_by' => $createurId,
                            'updated_by' => $createurId,
                        ]
                    );
                    $indicator->sourcesVerification()->syncWithoutDetaching([$sourceVerification->id]);

                    foreach (['T1', 'T2', 'T3', 'T4'] as $quarterIndex => $quarter) {
                        $value = min(100, max(10, $this->taux($exercice->annee, $activityIndex) - (3 - $quarterIndex) * 9));
                        Mesure::updateOrCreate(
                            ['indicateur_id' => $indicator->id, 'periode' => $exercice->annee.'-'.$quarter],
                            [
                                'saisi_par' => $createurId,
                                'verifie_par' => $createurId,
                                'verifie_le' => now(),
                                'valide_par' => $createurId,
                                'valide_le' => now(),
                                'valeur_realisee' => $value,
                                'taux_atteinte' => $value,
                                'feu' => $value >= 80 ? 'vert' : ($value >= 60 ? 'orange' : 'rouge'),
                                'commentaire' => 'Mesure de demonstration '.$quarter.' '.$exercice->annee.'.',
                                'statut' => 'validee',
                                'version' => 1,
                            ]
                        );
                    }
                }
            }

            $budget->update(['montant_total' => $totalBudget]);
        }
    }

    private function structure(string $code, string $libelle, string $sigle, string $type, ?int $parentId, int $niveau, int $ordre): Structure
    {
        return Structure::updateOrCreate(
            ['code' => $code],
            [
                'libelle' => $libelle,
                'sigle' => $sigle,
                'type' => $type,
                'parent_id' => $parentId,
                'niveau' => $niveau,
                'ordre' => $ordre,
                'actif' => true,
            ]
        );
    }

    private function taux(int $annee, int $index): float
    {
        return match (true) {
            $annee <= 2024 => 92.0 - $index,
            $annee === 2025 => 84.0 - $index,
            $annee === 2026 => 68.0 + $index,
            default => 28.0 + ($index * 6),
        };
    }
}
