<?php

namespace Tests\Feature;

use App\Models\Exercice;
use App\Models\User;
use Database\Seeders\ExerciceSeeder;
use Database\Seeders\NomenclatureBudgetaireSeeder;
use Database\Seeders\PartenaireFinancierSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SourceFinancementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/** Tests Phase D — Hub référentiel financier + exports Excel/PDF. */
class ReferentielFinancierPhaseDTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciceSeeder::class);
        $this->seed(SourceFinancementSeeder::class);
        $this->seed(PartenaireFinancierSeeder::class);
        $this->seed(NomenclatureBudgetaireSeeder::class);
        $this->admin = User::create([
            'name' => 'Admin Phase D', 'email' => 'admin.phased@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $this->admin->assignRole('administrateur');
    }

    public function test_hub_referentiel_accessible(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);

        $this->actingAs($this->admin)
            ->get(route('referentiel.hub', ['exercice' => $exercice->id]))
            ->assertOk()
            ->assertSee('Référentiel financier CEEAC')
            ->assertSee('Exports référentiel');
    }

    public function test_hub_refuse_utilisateur_sans_permission(): void
    {
        $user = User::create([
            'name' => 'Sans droit', 'email' => 'sans@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);

        $this->actingAs($user)
            ->get(route('referentiel.hub'))
            ->assertForbidden();
    }

    public function test_exports_excel_referentiel(): void
    {
        Excel::fake();
        $suffix = now()->format('Ymd');

        $this->actingAs($this->admin)->get(route('referentiel.export.sources'))->assertOk();
        Excel::assertDownloaded('referentiel-sources-'.$suffix.'.xlsx');

        $this->actingAs($this->admin)->get(route('referentiel.export.partenaires'))->assertOk();
        Excel::assertDownloaded('referentiel-ptf-'.$suffix.'.xlsx');

        $exercice = Exercice::firstWhere('annee', 2026);
        $this->actingAs($this->admin)
            ->get(route('referentiel.export.conventions', ['exercice' => $exercice->id]))
            ->assertOk();
        Excel::assertDownloaded('referentiel-conventions-'.$suffix.'.xlsx');
    }

    public function test_exports_pdf_referentiel(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);

        foreach (['referentiel.pdf.synthese', 'referentiel.pdf.sources', 'referentiel.pdf.ptf'] as $route) {
            $this->actingAs($this->admin)
                ->get(route($route, ['exercice' => $exercice->id]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_export_sources_refuse_sans_permission(): void
    {
        $user = User::create([
            'name' => 'Lecteur', 'email' => 'lecteur@ceeac.test',
            'password' => bcrypt('x'), 'actif' => true, 'statut' => 'actif',
        ]);
        $user->givePermissionTo('source_financement.voir');

        $this->actingAs($user)
            ->get(route('referentiel.export.sources'))
            ->assertForbidden();
    }
}
