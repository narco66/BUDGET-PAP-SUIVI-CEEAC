<?php

namespace Database\Seeders\Demo;

use App\Models\Exercice;
use App\Models\Immobilisation;
use App\Models\Structure;
use Illuminate\Database\Seeder;

/** Immobilisations de démonstration (non destructif, idempotent par numéro). */
class ImmobilisationsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            return;
        }

        $sg = Structure::where('code', 'SG')->value('id');

        // [numero, designation, categorie, valeur, duree, statut, compte, localisation]
        $items = [
            ['IMM-2026-001', 'Serveur virtualisation Dell PowerEdge', 'informatique', 18_500_000, 5, 'en_service', '244100', 'Datacenter SG'],
            ['IMM-2026-002', 'Parc ordinateurs portables (15 postes)', 'informatique', 12_000_000, 3, 'en_service', '244200', 'Open space SG'],
            ['IMM-2026-003', 'Toyota Land Cruiser — plaque CE-1234-A', 'vehicule', 45_000_000, 5, 'en_service', '245100', 'Parc automobile CEEAC'],
            ['IMM-2026-004', 'Aménagement salle de conférence', 'mobilier', 8_750_000, 10, 'en_service', '244300', 'Bâtiment principal'],
            ['IMM-2026-005', 'Extension aile administrative', 'batiment', 320_000_000, 20, 'en_service', '231100', 'Siège Libreville'],
        ];

        foreach ($items as [$numero, $designation, $categorie, $valeur, $duree, $statut, $compte, $localisation]) {
            Immobilisation::firstOrCreate(
                ['numero' => $numero],
                [
                    'exercice_id' => $exercice->id,
                    'structure_id' => $sg,
                    'categorie' => $categorie,
                    'designation' => $designation,
                    'date_acquisition' => ($exercice->date_debut ?? now())->toDateString(),
                    'valeur_acquisition' => $valeur,
                    'duree_amortissement_ans' => $duree,
                    'compte_syscohada' => $compte,
                    'localisation' => $localisation,
                    'statut' => $statut,
                ]
            );
        }
    }
}
