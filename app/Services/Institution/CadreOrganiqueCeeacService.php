<?php

namespace App\Services\Institution;

use App\Models\Structure;
use Illuminate\Support\Facades\DB;

class CadreOrganiqueCeeacService
{
    /** Synchronise le cadre organique CEEAC de facon idempotente et non destructive. */
    public function synchroniser(): array
    {
        return DB::transaction(function () {
            $stats = ['crees' => 0, 'mis_a_jour' => 0, 'renommes' => 0];

            $upsert = function (string $code, array $data, array $anciensLibelles = []) use (&$stats): Structure {
                $structure = Structure::withTrashed()->where('code', $code)->first();

                if (! $structure && $anciensLibelles) {
                    $structure = Structure::withTrashed()
                        ->whereIn('libelle', $anciensLibelles)
                        ->orderBy('id')
                        ->first();

                    if ($structure) {
                        $stats['renommes']++;
                    }
                }

                $created = false;
                if (! $structure) {
                    $structure = new Structure;
                    $structure->code = $code;
                    $created = true;
                }

                $structure->fill(array_merge($data, ['actif' => true]));
                $structure->code = $code;
                $structure->deleted_at = null;
                $structure->save();

                $stats[$created ? 'crees' : 'mis_a_jour']++;

                return $structure;
            };

            $pres = $upsert('PRES', [
                'libelle' => 'Presidence de la Commission',
                'sigle' => 'PRES',
                'type' => 'presidence',
                'parent_id' => null,
                'niveau' => 0,
                'ordre' => 10,
            ]);

            $dpres = $upsert('DPRES', [
                'libelle' => 'Presidence',
                'sigle' => 'DPRES',
                'type' => 'departement',
                'parent_id' => $pres->id,
                'niveau' => 1,
                'ordre' => 100,
            ]);

            $president = $upsert('PRESIDENT', [
                'libelle' => 'President de la Commission',
                'sigle' => 'PC',
                'type' => 'cellule',
                'parent_id' => $dpres->id,
                'niveau' => 2,
                'ordre' => 10,
            ]);

            $cabPres = $upsert('CAB-PRES', [
                'libelle' => 'Cabinet du President',
                'sigle' => 'CAB-PRES',
                'type' => 'direction',
                'parent_id' => $president->id,
                'niveau' => 3,
                'ordre' => 10,
            ]);

            $this->upsertMany($upsert, $cabPres, [
                ['DIRCAB-PRES', 'Directeur de Cabinet', 'DIRCAB', 'cellule', 10],
                ['CONS-POL', 'Conseiller Politique', 'CPOL', 'cellule', 20],
                ['CONS-ECO', 'Conseiller Economique', 'CECO', 'cellule', 30],
                ['CONS-DIP', 'Conseiller Diplomatique', 'CDIP', 'cellule', 40],
                ['SVC-COURRIER-PRES', 'Service Courrier', 'SC', 'service', 70],
            ], 4);

            $bcj = $upsert('BCJ', [
                'libelle' => 'Bureau du Conseiller Juridique',
                'sigle' => 'BCJ',
                'type' => 'direction',
                'parent_id' => $cabPres->id,
                'niveau' => 4,
                'ordre' => 50,
            ], ['Direction des Affaires Juridiques']);

            $this->upsertMany($upsert, $bcj, [
                ['SVC-CADS', 'Service Conventions, Accords et Documents Solennels', 'SCADS', 'service', 10],
                ['SVC-ARC', 'Service Affaires Reglementaires et Contentieuses', 'SARC', 'service', 20],
            ], 5);

            $vpres = $upsert('VPRES', [
                'libelle' => 'Vice-Presidence de la Commission',
                'sigle' => 'VPRES',
                'type' => 'vice_presidence',
                'parent_id' => null,
                'niveau' => 0,
                'ordre' => 20,
            ]);

            $dvp = $upsert('DVP', [
                'libelle' => 'Vice-Presidence',
                'sigle' => 'DVP',
                'type' => 'departement',
                'parent_id' => $vpres->id,
                'niveau' => 1,
                'ordre' => 110,
            ]);

            $vicePresident = $upsert('VICE-PRESIDENT', [
                'libelle' => 'Vice-President',
                'sigle' => 'VP',
                'type' => 'cellule',
                'parent_id' => $dvp->id,
                'niveau' => 2,
                'ordre' => 10,
            ]);

            $cabVp = $upsert('CAB-VP', [
                'libelle' => 'Cabinet du Vice-President',
                'sigle' => 'CAB-VP',
                'type' => 'direction',
                'parent_id' => $vicePresident->id,
                'niveau' => 3,
                'ordre' => 10,
            ]);

            $this->upsertMany($upsert, $cabVp, [
                ['CHEF-CAB-VP', 'Chef de Cabinet', 'CCAB', 'cellule', 10],
                ['CHARGE-ETUDES-1', "Charge d'Etudes 1", 'CE1', 'cellule', 20],
                ['CHARGE-ETUDES-2', "Charge d'Etudes 2", 'CE2', 'cellule', 30],
            ], 4);

            $sg = $upsert('SG', [
                'libelle' => 'Secretariat General',
                'sigle' => 'SG',
                'type' => 'secretariat_general',
                'parent_id' => $pres->id,
                'niveau' => 1,
                'ordre' => 30,
            ], ['Secretariat Administratif', 'Secrétariat Administratif']);

            $departements = [
                ['DPRES', 'Presidence', 'DPRES', $pres->id, 1, 100],
                ['DVP', 'Vice-Presidence', 'DVP', $vpres->id, 1, 110],
                ['DAPPS', 'Affaires Politiques, Paix et Securite', 'DAPPS', $pres->id, 1, 120],
                ['DMCAEMF', 'Marche Commun, Affaires Economiques, Monetaires et Financieres', 'DMCAEMF', $pres->id, 1, 130],
                ['DERNADR', 'Environnement, Ressources Naturelles, Agriculture et Developpement Rural', 'DERNADR', $pres->id, 1, 140],
                ['DATI', 'Amenagement du Territoire et Infrastructures', 'DATI', $pres->id, 1, 150],
                ['DPGDHS', 'Promotion du Genre, Developpement Humain et Social', 'DPGDHS', $pres->id, 1, 160],
                ['DSG', 'Secretariat General', 'DSG', $sg->id, 2, 170],
            ];

            $departementsParCode = [];
            foreach ($departements as [$code, $libelle, $sigle, $parentId, $niveau, $ordre]) {
                $departementsParCode[$code] = $upsert($code, [
                    'libelle' => $libelle,
                    'sigle' => $sigle,
                    'type' => 'departement',
                    'parent_id' => $parentId,
                    'niveau' => $niveau,
                    'ordre' => $ordre,
                ]);
            }

            $directionsSg = [
                ['DIR-CRPP', 'Direction Communication, Relations Publiques et Protocole', 'DCRPP', [
                    ['SVC-CRP', 'Service Communication et Relations Publiques', 'SCRP'],
                    ['CENTRE-DOC-ARCH', 'Centre de Documentation et Archives', 'CDA'],
                    ['SVC-PROT-CER', 'Service Protocole et Ceremonial', 'SPC'],
                    ['SVC-TRAD-INT', 'Service Traduction et Interpretariat', 'STI'],
                ]],
                ['DIR-CMR', 'Direction Cooperation et Mobilisation des Ressources', 'DCMR', [
                    ['SVC-COOP', 'Service Cooperation', 'SCOOP'],
                    ['SVC-MOB-RESS', 'Service Mobilisation des Ressources', 'SMR'],
                ]],
                ['DIR-PPB', 'Direction Planification, Programmation et Budget', 'DPPB', [
                    ['SVC-PLAN-SE', 'Service Planification et Suivi-Evaluation', 'SPSE'],
                    ['SVC-PROG-PROJ', 'Service Programmes et Projets', 'SPP'],
                    ['SVC-BUDGET', 'Service Budget', 'SB'],
                ]],
                ['DIR-RHMG', 'Direction Ressources Humaines et Moyens Generaux', 'DRHMG', [
                    ['SVC-ADMIN-RH', 'Service Administration RH', 'SARH'],
                    ['SVC-DEV-RH', 'Service Developpement RH', 'SDRH'],
                    ['SVC-MG', 'Service Moyens Generaux', 'SMG'],
                ]],
                ['DIR-SI', "Direction des Systemes d'Information", 'DSI', [
                    ['SVC-ETUDES-DEV', 'Service Etudes et Developpement', 'SED'],
                    ['SVC-EXP-MAINT', 'Service Exploitation et Maintenance', 'SEM'],
                ]],
            ];

            foreach ($directionsSg as [$code, $libelle, $sigle, $services]) {
                $direction = $upsert($code, [
                    'libelle' => $libelle,
                    'sigle' => $sigle,
                    'type' => 'direction',
                    'parent_id' => $departementsParCode['DSG']->id,
                    'niveau' => 3,
                    'ordre' => 10 + count($services),
                ]);

                foreach ($services as $index => [$serviceCode, $serviceLibelle, $serviceSigle]) {
                    $upsert($serviceCode, [
                        'libelle' => $serviceLibelle,
                        'sigle' => $serviceSigle,
                        'type' => 'service',
                        'parent_id' => $direction->id,
                        'niveau' => 4,
                        'ordre' => ($index + 1) * 10,
                    ]);
                }
            }

            $this->synchroniserDepartementsMetiers($upsert, $departementsParCode);
            app(AliasDemonstrationService::class)->rapprocher();
            $this->normaliserLibellesObsoletes();

            return $stats;
        });
    }

    /** Directions et services des départements métier CEEAC (phase A1). */
    private function synchroniserDepartementsMetiers(callable $upsert, array $departementsParCode): void
    {
        $cartographie = [
            'DAPPS' => [
                ['DIR-APPS-GOUV', 'Direction Gouvernance et Affaires Politiques', 'DAGP', [
                    ['SVC-APPS-GOUV', 'Service Gouvernance Institutionnelle', 'SGI'],
                    ['SVC-APPS-DIPL', 'Service Cooperation Politique', 'SCP'],
                ]],
                ['DIR-APPS-PAIX', 'Direction Paix, Securite et Stabilisation', 'DAPS', [
                    ['SVC-APPS-PAIX', 'Service Paix et Securite', 'SPS'],
                    ['SVC-APPS-CRISE', 'Service Prevention et Gestion des Crises', 'SPGC'],
                ]],
            ],
            'DMCAEMF' => [
                ['DIR-MC', 'Direction du Marche Commun', 'DMC', [
                    ['SVC-MC-CUSTOMS', 'Service Douanes et Transit', 'SDT'],
                    ['SVC-MC-NORMES', 'Service Normes et Qualite', 'SNQ'],
                ]],
                ['DIR-AEMF', 'Direction Affaires Economiques, Monetaires et Financieres', 'DAEMF', [
                    ['SVC-AEMF-ECO', 'Service Integration Economique', 'SIE'],
                    ['SVC-AEMF-FIN', 'Service Cooperation Financiere', 'SCF'],
                ]],
            ],
            'DERNADR' => [
                ['DIR-ENV', 'Direction Environnement et Ressources Naturelles', 'DERN', [
                    ['SVC-ENV', 'Service Environnement', 'SENV'],
                    ['SVC-RN', 'Service Ressources Naturelles', 'SRN'],
                ]],
                ['DIR-AGRI', 'Direction Agriculture et Developpement Rural', 'DADR', [
                    ['SVC-AGRI', 'Service Agriculture', 'SAGR'],
                    ['SVC-RURAL', 'Service Developpement Rural', 'SDR'],
                ]],
            ],
            'DATI' => [
                ['DIR-AT', 'Direction Amenagement du Territoire', 'DAT', [
                    ['SVC-AT', 'Service Amenagement du Territoire', 'SAT'],
                    ['SVC-URB', 'Service Urbanisme et Habitat', 'SUH'],
                ]],
                ['DIR-INFRA', 'Direction Infrastructures', 'DINF', [
                    ['SVC-INFRA-ROUT', 'Service Routes et Transport', 'SRT'],
                    ['SVC-INFRA-TRANS', 'Service Transport Regional', 'STR'],
                ]],
            ],
            'DPGDHS' => [
                ['DIR-GDH', 'Direction Genre et Developpement Humain', 'DGDH', [
                    ['SVC-GDH-GENRE', 'Service Promotion du Genre', 'SPG'],
                    ['SVC-GDH-FORM', 'Service Formation et Competences', 'SFC'],
                ]],
                ['DIR-SOC', 'Direction Affaires Sociales et Culture', 'DASC', [
                    ['SVC-SOC', 'Service Affaires Sociales', 'SAS'],
                    ['SVC-CULT', 'Service Culture et Patrimoine', 'SCULP'],
                ]],
            ],
        ];

        foreach ($cartographie as $codeDept => $directions) {
            if (! isset($departementsParCode[$codeDept])) {
                continue;
            }

            $dept = $departementsParCode[$codeDept];
            $ordreDir = 10;

            foreach ($directions as [$codeDir, $libelleDir, $sigleDir, $services]) {
                $direction = $upsert($codeDir, [
                    'libelle' => $libelleDir,
                    'sigle' => $sigleDir,
                    'type' => 'direction',
                    'parent_id' => $dept->id,
                    'niveau' => $dept->niveau + 1,
                    'ordre' => $ordreDir,
                ]);
                $ordreDir += 10;

                foreach ($services as $index => [$serviceCode, $serviceLibelle, $serviceSigle]) {
                    $upsert($serviceCode, [
                        'libelle' => $serviceLibelle,
                        'sigle' => $serviceSigle,
                        'type' => 'service',
                        'parent_id' => $direction->id,
                        'niveau' => $direction->niveau + 1,
                        'ordre' => ($index + 1) * 10,
                    ]);
                }
            }
        }
    }

    private function upsertMany(callable $upsert, Structure $parent, array $items, int $niveau): void
    {
        foreach ($items as [$code, $libelle, $sigle, $type, $ordre]) {
            $upsert($code, [
                'libelle' => $libelle,
                'sigle' => $sigle,
                'type' => $type,
                'parent_id' => $parent->id,
                'niveau' => $niveau,
                'ordre' => $ordre,
            ]);
        }
    }

    private function normaliserLibellesObsoletes(): void
    {
        Structure::query()
            ->where('libelle', 'Secrétariat Administratif')
            ->orWhere('libelle', 'Secretariat Administratif')
            ->update(['libelle' => 'Secretariat General', 'type' => 'secretariat_general']);

        Structure::query()
            ->where('libelle', 'Direction des Affaires Juridiques')
            ->update(['libelle' => 'Bureau du Conseiller Juridique']);
    }
}
