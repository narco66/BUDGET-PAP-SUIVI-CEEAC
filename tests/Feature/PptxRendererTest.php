<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\User;
use App\Services\Reporting\PptxRenderer;
use Database\Seeders\ReportingPermissionsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P1 — Format PowerPoint (.pptx) : nouveau rendu branché sur les sections normalisées (même source
 * unique que PDF/Excel/Word/CSV), complétant les 5 formats exigés par la charte de restitution.
 */
class PptxRendererTest extends TestCase
{
    use RefreshDatabase;

    private function composed(): array
    {
        return [
            'titre' => 'Rapport annuel institutionnel',
            'sous_titre' => 'Exercice 2026 — CEEAC',
            'sections' => [
                ['type' => 'kpi', 'titre' => 'Synthèse', 'items' => [
                    ['label' => 'Taux d’exécution', 'valeur' => 72.5, 'fmt' => 'percent'],
                    ['label' => 'Budget payé', 'valeur' => 1500000, 'fmt' => 'money'],
                ]],
                ['type' => 'table', 'titre' => 'Par axe', 'colonnes' => [
                    ['label' => 'Axe', 'fmt' => 'text'], ['label' => 'Payé', 'fmt' => 'money'],
                ], 'lignes' => [['Axe 1', 500000], ['Axe 2', 1000000]], 'total' => ['TOTAL', 1500000]],
                ['type' => 'liste', 'titre' => 'Recommandations', 'items' => ['Accélérer les paiements', 'Renforcer le suivi']],
                ['type' => 'text', 'titre' => 'Conclusion', 'contenu' => 'Exercice globalement satisfaisant.'],
            ],
        ];
    }

    public function test_rendu_pptx_produit_un_fichier_office_valide(): void
    {
        $meta = ['reference' => 'RPT-2026-000001', 'version' => 1, 'date' => '14/06/2026 10:00', 'verification' => 'https://ceeac/verif/x'];

        $bytes = app(PptxRenderer::class)->rendre($this->composed(), $meta, null);

        // Un .pptx est une archive ZIP OOXML : signature « PK\x03\x04 ».
        $this->assertStringStartsWith("PK\x03\x04", $bytes);
        $this->assertGreaterThan(2000, strlen($bytes));
    }

    public function test_generation_pptx_via_le_hub_reporting(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ReportingPermissionsSeeder::class);
        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex 2026', 'statut' => 'ouvert']);
        Storage::fake('local');

        // « president » dispose de reporting.generate (alias rapport.exporter).
        $president = User::factory()->create(['actif' => true])->assignRole('president');

        $this->actingAs($president)
            ->get(route('reporting.generer', ['type' => 'nomenclature', 'format' => 'pptx', 'exercice' => $exercice->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.presentationml.presentation');

        $this->assertDatabaseHas('rapports', ['type' => 'nomenclature', 'format' => 'pptx']);
    }
}
