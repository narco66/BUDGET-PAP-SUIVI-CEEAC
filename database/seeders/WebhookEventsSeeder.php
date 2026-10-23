<?php



namespace Database\Seeders;



use App\Models\WebhookEvent;

use Illuminate\Database\Seeder;



/** Catalogue des événements webhook interopérabilité (I-P2). */

class WebhookEventsSeeder extends Seeder

{

    /** @var array<int, array{code: string, libelle: string, module: string, description: string}> */

    private array $evenements = [

        ['code' => 'engagement.vise', 'libelle' => 'Engagement visé', 'module' => 'depense', 'description' => 'Visa contrôleur financier sur un engagement.'],

        ['code' => 'liquidation.valide', 'libelle' => 'Liquidation validée', 'module' => 'depense', 'description' => 'Liquidation validée dans le circuit dépense.'],

        ['code' => 'ordonnancement.valide', 'libelle' => 'Ordonnancement validé', 'module' => 'depense', 'description' => 'Ordonnancement validé dans le circuit dépense.'],

        ['code' => 'paiement.valide', 'libelle' => 'Paiement validé', 'module' => 'depense', 'description' => 'Paiement validé — alimente TOMPRO.'],

        ['code' => 'budget.adopte', 'libelle' => 'Budget adopté', 'module' => 'budget', 'description' => 'Budget institutionnel adopté par le circuit MOA.'],

        ['code' => 'budget.revision_validee', 'libelle' => 'Révision budgétaire validée', 'module' => 'budget', 'description' => 'Rectificatif ou virement budgétaire appliqué.'],

        ['code' => 'pap.publie', 'libelle' => 'PAP publié', 'module' => 'pap', 'description' => 'Plan d\'action prioritaire publié pour l\'exercice.'],

        ['code' => 'recette.encaissement_valide', 'libelle' => 'Encaissement validé', 'module' => 'recettes', 'description' => 'Encaissement comptabilisé après circuit RECETTES.'],

        ['code' => 'reporting.publie', 'libelle' => 'Rapport publié', 'module' => 'reporting', 'description' => 'Rapport institutionnel publié et verrouillé.'],

    ];



    public function run(): void

    {

        foreach ($this->evenements as $evt) {

            WebhookEvent::updateOrCreate(

                ['code' => $evt['code']],

                [...$evt, 'actif' => true]

            );

        }

    }

}

