<?php

namespace Tests\Recette;

use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\User;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportLotService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Recette du module Import sur la base PostgreSQL réelle (transaction annulée) : les référentiels existants
 * sont reconnus et protégés, et le Budget 2026 reste strictement inchangé (empreinte avant / après).
 */
class ImportRecetteTest extends RecettePgTestCase
{
    private function admin(): User
    {
        return User::query()->where('email', 'administrateur@ceeac.test')->firstOrFail();
    }

    private function csv(array $entetes, array $lignes): UploadedFile
    {
        $contenu = implode(';', $entetes)."\n".implode("\n", array_map(fn ($l) => implode(';', array_map(fn ($v) => str_replace(';', ',', (string) $v), $l)), $lignes))."\n";

        return UploadedFile::fake()->createWithContent('recette.csv', $contenu);
    }

    /** @return array{lignes: int, ouvert: string, engage: string} */
    private function empreinteBudget(int $exerciceId): array
    {
        $r = DB::selectOne('select count(*) n, coalesce(sum(montant_ouvert),0) ouvert, coalesce(sum(montant_engage),0) engage from lignes_budgetaires where exercice_id = ?', [$exerciceId]);

        return ['lignes' => (int) $r->n, 'ouvert' => (string) $r->ouvert, 'engage' => (string) $r->engage];
    }

    public function test_reimport_de_la_nomenclature_existante_tout_est_identique(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $rubriques = BudgetRubrique::query()->with('paragraphe.article.titre')->limit(25)->get();
        $lignes = $rubriques->map(fn ($r) => [$r->paragraphe->article->titre->sens, $r->paragraphe->article->titre->code, $r->paragraphe->article->titre->intitule,
            $r->paragraphe->article->code, $r->paragraphe->article->intitule, $r->paragraphe->code, $r->paragraphe->intitule, $r->code, $r->intitule])->all();
        $lots = app(ImportLotService::class);
        $lot = $lots->deposer($lots->creer('nomenclature', 'insertion', null, $admin), $this->csv(
            ['sens', 'titre_code', 'titre_intitule', 'article_code', 'article_intitule', 'paragraphe_code', 'paragraphe_intitule', 'rubrique_code', 'rubrique_intitule'], $lignes));

        fwrite(STDERR, "\nNomenclature : ".json_encode($lot->totaux)."\n");
        $this->assertSame(count($lignes), $lot->total('identique') + $lot->total('ignorer'), 'Les rubriques existantes sont reconnues.');
        $this->assertSame(0, $lot->total('inserer'));
    }

    public function test_reimport_des_structures_reconnues_et_protegees(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $structures = Structure::query()->with('parent')->orderBy('id')->limit(30)->get();
        $lots = app(ImportLotService::class);
        $lot = $lots->deposer($lots->creer('structures', 'mise_a_jour', null, $admin), $this->csv(['code', 'libelle', 'type', 'parent_code'],
            $structures->map(fn ($s) => [$s->code, $s->libelle, $s->type, $s->parent?->code])->all()));

        fwrite(STDERR, 'Structures : '.json_encode($lot->totaux)."\n");
        $this->assertSame(0, $lot->total('inserer') + $lot->total('mettre_a_jour') + $lot->total('erreur'), 'Aucune écriture sur un référentiel identique.');
    }

    public function test_budget_2026_strictement_protege(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $exercice = Exercice::query()->where('annee', 2026)->firstOrFail();
        $avant = $this->empreinteBudget($exercice->id);
        $existantes = LigneBudgetaire::query()->with('rubrique.paragraphe')->where('exercice_id', $exercice->id)->whereNotNull('rubrique_id')->orderBy('id')->limit(5)->get();
        $this->assertNotEmpty($existantes);

        $lignes = $existantes->map(fn ($l, $i) => [$l->code, $l->intitule, $l->rubrique->code, $l->rubrique->paragraphe->code,
            $i === 0 ? (float) $l->montant_ouvert + 1_000_000 : $l->montant_ouvert])->all();
        $lignes[] = ['LB-RECETTE-NOUVELLE', 'Ligne nouvelle de recette', $existantes[0]->rubrique->code, $existantes[0]->rubrique->paragraphe->code, '250000'];
        $lots = app(ImportLotService::class);
        $lot = $lots->deposer($lots->creer('lignes_budgetaires', 'mise_a_jour', $exercice->id, $admin), $this->csv(
            ['code', 'intitule', 'rubrique_code', 'paragraphe_code', 'montant_ouvert'], $lignes));

        fwrite(STDERR, 'Budget 2026 : statut '.$lot->statut.' '.json_encode($lot->totaux)."\n");
        $this->assertSame('conflit', $lot->lignes()->where('numero', 2)->value('operation'), 'Crédit modifié dans le fichier : conflit, jamais réécrit.');
        if ($lot->statut === 'simule' && $lot->total('a_ecrire') > 0) {
            $lot = $lots->soumettre($lot, $admin, true);
            $lot->update(['statut' => 'approuve']);
            $lot = app(ImportExecutionService::class)->lancer($lot->fresh(), $admin);
        }
        $this->assertSame('anomalies_detectees', $lot->statut, 'Budget 2026 protégé : la ligne nouvelle est bloquée.');
        $this->assertTrue($lot->anomalies()->where('regle', 'budget_protege')->exists());
        $apres = $this->empreinteBudget($exercice->id);
        $this->assertSame($avant, $apres, 'Empreinte du Budget 2026 strictement identique.');
        foreach ($existantes as $l) {
            $this->assertSame((string) $l->montant_ouvert, (string) $l->fresh()->montant_ouvert, "Crédit de {$l->code} inchangé.");
        }
        fwrite(STDERR, 'Empreinte Budget 2026 avant '.json_encode($avant).' après '.json_encode($apres).' (lot '.$lot->statut.")\n");
    }
}
