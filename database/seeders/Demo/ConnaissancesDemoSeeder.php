<?php

namespace Database\Seeders\Demo;

use App\Models\Ged\Connaissance;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Wiki & procédures de démonstration (P3-3). */
class ConnaissancesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstWhere('email', 'administrateur@ceeac.test');
        if (! $admin) {
            return;
        }

        $items = [
            [
                'reference' => 'CON-WIKI-001',
                'slug' => 'presentation-plateforme-ceeac',
                'titre' => 'Présentation de la plateforme GEST-BUDGET PAP',
                'type' => 'wiki',
                'module' => 'general',
                'resume' => 'Vue d\'ensemble du système intégré budget-PAP-suivi CEEAC.',
                'contenu' => "## Objectif\n\nPlateforme institutionnelle couvrant la **chaîne budgétaire complète** : PAP, budget, dépenses, recettes, suivi-évaluation et reporting.\n\n## Modules principaux\n\n- Plan Annuel de Performance (PAP)\n- Exécution budgétaire\n- GED documentaire\n- Reporting institutionnel",
                'statut' => 'publie',
                'publie_par_id' => $admin->id,
                'publie_le' => now()->subDays(10),
                'tags' => ['onboarding', 'ceeac'],
            ],
            [
                'reference' => 'CON-PROC-001',
                'slug' => 'procedure-validation-engagement',
                'titre' => 'Procédure de validation d\'un engagement',
                'type' => 'procedure',
                'module' => 'depense',
                'categorie' => 'Engagements',
                'resume' => 'Circuit de validation d\'un engagement budgétaire selon la procédure CEEAC.',
                'contenu' => "## Étapes\n\n1. **Expression de besoin** validée par le chef de service\n2. **Engagement** saisi par le gestionnaire budgétaire\n3. **Contrôle financier** (montant, disponibilité crédits, pièces)\n4. **Validation** par le contrôleur financier\n5. **Archivage GED** du certificat de réservation\n\n## Délais cibles\n\n- Contrôle initial : 48 h ouvrées\n- Validation finale : 72 h ouvrées",
                'statut' => 'publie',
                'publie_par_id' => $admin->id,
                'publie_le' => now()->subDays(5),
                'tags' => ['engagement', 'validation', 'workflow'],
            ],
            [
                'reference' => 'CON-PROC-002',
                'slug' => 'procedure-depot-document-ged',
                'titre' => 'Procédure de dépôt documentaire (GED)',
                'type' => 'procedure',
                'module' => 'ged',
                'categorie' => 'GED',
                'resume' => 'Règles de nommage, classification et dépôt des pièces justificatives.',
                'contenu' => "## Avant le dépôt\n\n- Vérifier le **format** (PDF de préférence)\n- Nommer le fichier : `TYPE_OBJET_DATE.pdf`\n- Choisir la **catégorie** et le **niveau de confidentialité**\n\n## Après le dépôt\n\n- Contrôler l\'indexation OCR si activée\n- Lancer le circuit de validation si requis",
                'statut' => 'publie',
                'publie_par_id' => $admin->id,
                'publie_le' => now()->subDays(3),
                'tags' => ['ged', 'document'],
            ],
        ];

        foreach ($items as $data) {
            Connaissance::firstOrCreate(
                ['reference' => $data['reference']],
                $data + ['auteur_id' => $admin->id, 'version' => 1]
            );
        }
    }
}
