<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\LigneBudgetaireDetail;
use App\Services\Depense\ImputationPapService;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importe le détail à 5 chiffres du budget de fonctionnement (fichier « code;intitule;montant ») et le rattache
 * à la ligne budgétaire existante dont le code est le plus long préfixe (ex. 66321 → 663). Idempotent ;
 * ne modifie aucune ligne budgétaire ni aucun montant du budget.
 */
class BudgetImporterDetailCommand extends Command
{
    protected $signature = 'budget:importer-detail
                            {fichier=database/data/budget_2026_fonctionnement_detail.csv}
                            {--exercice=2026}
                            {--source=CEEAC_Budget_2026.pdf}
                            {--dry-run : contrôle sans écriture}';

    protected $description = 'Importe le détail à 5 chiffres du budget de fonctionnement, rattaché aux lignes budgétaires existantes';

    public function handle(): int
    {
        $exercice = Exercice::query()->where('annee', (int) $this->option('exercice'))->first();
        if (! $exercice || ! is_readable($this->argument('fichier'))) {
            $this->error('Exercice ou fichier introuvable.');

            return self::FAILURE;
        }

        $parents = LigneBudgetaire::query()->where('exercice_id', $exercice->id)->get()
            ->reject(fn ($l) => ImputationPapService::estEnveloppePap($l))
            ->mapWithKeys(fn ($l) => [ImputationPapService::codeBudget($l) => $l])
            ->sortByDesc(fn ($l, $code) => strlen((string) $code));

        $lignes = array_map(fn ($l) => str_getcsv($l, ';'), array_filter(array_map('trim', file($this->argument('fichier')))));
        array_shift($lignes);

        $rattachements = [];
        $orphelins = [];
        foreach ($lignes as [$code, $intitule, $montant]) {
            $parent = $parents->first(fn ($l, $c) => str_starts_with($code, (string) $c));
            $parent ? $rattachements[] = compact('code', 'intitule', 'montant', 'parent') : $orphelins[] = $code;
        }
        if ($orphelins) {
            $this->error('Codes sans ligne budgétaire parente : '.implode(', ', $orphelins));

            return self::FAILURE;
        }

        // Contrôle de cohérence : somme du détail par ligne parente, comparée au crédit de la ligne.
        $tableau = collect($rattachements)->groupBy(fn ($r) => $r['parent']->id)->map(function ($g) {
            $parent = $g->first()['parent'];
            $somme = $g->sum(fn ($r) => (float) $r['montant']);
            $alloue = (float) ($parent->montant_revise ?? $parent->montant_ouvert);

            return [ImputationPapService::codeBudget($parent), $parent->intitule, $g->count(), number_format($somme, 0, ',', ' '), number_format($alloue, 0, ',', ' '), abs($somme - $alloue) < 0.5 ? 'OK' : 'écart '.number_format($somme - $alloue, 0, ',', ' ')];
        })->values();
        $this->table(['Ligne', 'Intitulé', 'Détails', 'Somme détail', 'Crédit ligne', 'Contrôle'], $tableau->all());

        if ($this->option('dry-run')) {
            $this->comment('Simulation : aucune écriture.');

            return self::SUCCESS;
        }

        $crees = 0;
        DB::transaction(function () use ($rattachements, $exercice, &$crees) {
            foreach ($rattachements as $r) {
                $detail = LigneBudgetaireDetail::query()->updateOrCreate(
                    ['exercice_id' => $exercice->id, 'code' => $r['code']],
                    ['ligne_budgetaire_id' => $r['parent']->id, 'intitule' => $r['intitule'], 'montant_prevu' => $r['montant'], 'source' => $this->option('source')],
                );
                $crees += (int) $detail->wasRecentlyCreated;
            }
            AuditLogger::evenement('budget_detail_importe', null, ['module' => 'BUDGET', 'apres' => [
                'exercice' => $exercice->annee, 'fichier' => basename($this->argument('fichier')), 'source' => $this->option('source'),
                'lignes' => count($rattachements), 'creees' => $crees,
            ]]);
        });
        $this->info(count($rattachements)." détail(s) traité(s), {$crees} créé(s).");

        return self::SUCCESS;
    }
}
