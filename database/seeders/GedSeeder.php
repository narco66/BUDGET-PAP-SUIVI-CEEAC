<?php

namespace Database\Seeders;

use App\Models\Ged\GedCategorie;
use App\Models\Ged\GedConservationRule;
use App\Models\Ged\GedDossier;
use App\Models\Ged\GedTypeDocument;
use App\Models\Ged\GedWorkflow;
use App\Models\Ged\GedWorkflowEtape;
use Illuminate\Database\Seeder;

/** Données de référence GED — seeder non destructif (updateOrCreate). */
class GedSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GedPermissionsSeeder::class);

        $catBudget = GedCategorie::updateOrCreate(
            ['code' => 'BUDGET'],
            ['libelle' => 'Documents budgétaires', 'description' => 'Nomenclature, crédits, exécution', 'ordre' => 1, 'actif' => true]
        );

        $catPap = GedCategorie::updateOrCreate(
            ['code' => 'PAP'],
            ['libelle' => 'Plan Annuel de Performance', 'description' => 'PAP, indicateurs, rapports', 'ordre' => 2, 'actif' => true]
        );

        $catDepense = GedCategorie::updateOrCreate(
            ['code' => 'DEPENSE'],
            ['libelle' => 'Chaîne de la dépense', 'description' => 'Besoins, engagements, paiements', 'ordre' => 3, 'actif' => true]
        );

        $catDecision = GedCategorie::updateOrCreate(
            ['code' => 'DECISION'],
            ['libelle' => 'Décisions & actes', 'description' => 'Décisions, PV, correspondances', 'ordre' => 4, 'actif' => true]
        );

        $typePv = GedTypeDocument::updateOrCreate(
            ['code' => 'PV'],
            ['libelle' => 'Procès-verbal', 'categorie_id' => $catDecision->id, 'signature_requise' => true, 'actif' => true,
                'extensions_autorisees' => ['pdf', 'doc', 'docx']]
        );

        $typeJustif = GedTypeDocument::updateOrCreate(
            ['code' => 'JUSTIF'],
            ['libelle' => 'Pièce justificative', 'categorie_id' => $catDepense->id, 'actif' => true,
                'extensions_autorisees' => ['pdf', 'jpg', 'jpeg', 'png']]
        );

        $typeRapport = GedTypeDocument::updateOrCreate(
            ['code' => 'RAPPORT'],
            ['libelle' => 'Rapport institutionnel', 'categorie_id' => $catPap->id, 'signature_requise' => true, 'actif' => true]
        );

        GedDossier::updateOrCreate(
            ['reference' => 'CEEAC/GED/GED/DOSSIER/2026/000001'],
            ['titre' => 'Dossier général 2026', 'description' => 'Dossier racine institutionnel', 'module_source' => 'GED']
        );

        GedConservationRule::updateOrCreate(
            ['type_document_id' => $typePv->id, 'categorie_id' => null],
            ['duree_annees' => null, 'sort_final' => 'conservation_permanente', 'alerte_jours_avant' => 90, 'verrouillage' => true, 'actif' => true]
        );

        GedConservationRule::updateOrCreate(
            ['type_document_id' => $typeJustif->id, 'categorie_id' => null],
            ['duree_annees' => 10, 'sort_final' => 'archivage_definitif', 'alerte_jours_avant' => 30, 'actif' => true]
        );

        $workflow = GedWorkflow::updateOrCreate(
            ['code' => 'STANDARD'],
            ['libelle' => 'Circuit standard CEEAC', 'description' => 'Dépôt → contrôle → validation → signature → publication → archivage', 'actif' => true]
        );

        $etapes = [
            ['ordre' => 1, 'code' => 'depot', 'libelle' => 'Dépôt', 'type_etape' => 'depot'],
            ['ordre' => 2, 'code' => 'controle', 'libelle' => 'Contrôle', 'type_etape' => 'controle', 'delai_jours' => 3],
            ['ordre' => 3, 'code' => 'validation', 'libelle' => 'Validation', 'type_etape' => 'validation', 'delai_jours' => 5],
            ['ordre' => 4, 'code' => 'signature', 'libelle' => 'Signature', 'type_etape' => 'signature', 'delai_jours' => 7],
            ['ordre' => 5, 'code' => 'publication', 'libelle' => 'Publication', 'type_etape' => 'publication'],
            ['ordre' => 6, 'code' => 'archivage', 'libelle' => 'Archivage', 'type_etape' => 'archivage'],
        ];

        foreach ($etapes as $e) {
            GedWorkflowEtape::updateOrCreate(
                ['workflow_id' => $workflow->id, 'ordre' => $e['ordre']],
                array_merge($e, ['workflow_id' => $workflow->id, 'notification' => true])
            );
        }
    }
}
