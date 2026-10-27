<?php

namespace Database\Seeders;

use App\Models\Workflow;
use Illuminate\Database\Seeder;

/**
 * Circuits d'approbation des imports sensibles (module Import). Additif et idempotent : ne touche à aucun
 * autre circuit. L'approbateur reçoit une tâche « Mes Tâches » et une notification ; l'initiateur du lot ne
 * peut pas l'approuver (séparation des fonctions du moteur). Les rôles sont modifiables par l'administration
 * des circuits.
 */
class ImportWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'IMPORT_REFERENTIEL' => ['Approbation d\'import — référentiel organique', 'Approbation Secrétaire général', 'secretaire_general'],
            'IMPORT_BUDGET' => ['Approbation d\'import — nomenclature et budget', 'Approbation Direction du Budget', 'directeur'],
        ] as $code => [$libelle, $etape, $role]) {
            $workflow = Workflow::query()->updateOrCreate(['code' => $code], ['libelle' => $libelle, 'type_objet' => 'import_lot', 'actif' => true]);
            $workflow->etapes()->updateOrCreate(['ordre' => 1], ['libelle' => $etape, 'role' => $role, 'delai_jours' => 3]);
        }
    }
}
