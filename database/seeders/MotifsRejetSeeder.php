<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Référentiel des motifs de rejet workflow (P0). */
class MotifsRejetSeeder extends Seeder
{
    public function run(): void
    {
        $motifs = [
            ['code' => 'ADM-001', 'categorie' => 'administratif', 'libelle' => 'Dossier incomplet', 'ordre' => 10],
            ['code' => 'ADM-002', 'categorie' => 'administratif', 'libelle' => 'Non-conformité procédure interne', 'ordre' => 20],
            ['code' => 'BUD-001', 'categorie' => 'budgetaire', 'libelle' => 'Crédits insuffisants', 'ordre' => 30],
            ['code' => 'BUD-002', 'categorie' => 'budgetaire', 'libelle' => 'Ventilation budgétaire incorrecte', 'ordre' => 40],
            ['code' => 'TEC-001', 'categorie' => 'technique', 'libelle' => 'Justification technique insuffisante', 'ordre' => 50],
            ['code' => 'FIN-001', 'categorie' => 'financier', 'libelle' => 'Montant ou calcul erroné', 'ordre' => 60],
            ['code' => 'DOC-001', 'categorie' => 'documentaire', 'libelle' => 'Pièces justificatives manquantes ou invalides', 'ordre' => 70],
        ];

        foreach ($motifs as $m) {
            DB::table('motifs_rejet')->updateOrInsert(
                ['code' => $m['code']],
                array_merge($m, ['actif' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
