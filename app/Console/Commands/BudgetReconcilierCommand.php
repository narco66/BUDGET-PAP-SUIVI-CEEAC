<?php

namespace App\Console\Commands;

use App\Models\Activite;
use App\Models\ExpressionBesoin;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Services\Budget\RbmBudgetSyncService;
use Illuminate\Console\Command;

/** Réconcilie les liaisons RBM ↔ budget et les imputations amont des besoins. */
class BudgetReconcilierCommand extends Command
{
    protected $signature = 'budget:reconcilier {--exercice= : ID ou année de l\'exercice} {--dry-run : Simulation sans écriture}';

    protected $description = 'Synchronise Activité↔Ligne budgétaire et rattache les besoins à leur ligne';

    public function handle(RbmBudgetSyncService $sync): int
    {
        $exerciceId = null;
        if ($this->option('exercice')) {
            $opt = $this->option('exercice');
            $exerciceId = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
        }

        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('Mode simulation — aucune modification en base.');
        }

        $stats = ['activites' => 0, 'lignes' => 0, 'besoins' => 0];

        $activites = Activite::query()
            ->when($exerciceId, fn ($q) => $q->whereHas('sousProduit.produit.axe.pilier', fn ($p) => $p->where('exercice_id', $exerciceId)))
            ->whereNotNull('ligne_budgetaire_id')
            ->get();

        foreach ($activites as $activite) {
            if ($dry) {
                $ligne = LigneBudgetaire::find($activite->ligne_budgetaire_id);
                // Enveloppe PAP partagée : pas de lien inverse unique (même règle que RbmBudgetSyncService).
                if ($ligne && ! \App\Services\Depense\ImputationPapService::estEnveloppePap($ligne) && (int) $ligne->activite_id !== (int) $activite->id) {
                    $this->line("  Activité {$activite->code} → ligne {$ligne->code} (activite_id à corriger)");
                    $stats['activites']++;
                }
            } else {
                $sync->synchroniserActivite($activite);
                $stats['activites']++;
            }
        }

        $lignes = LigneBudgetaire::query()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('activite_id')
            ->get();

        foreach ($lignes as $ligne) {
            if ($dry) {
                $activite = Activite::find($ligne->activite_id);
                if ($activite && (int) $activite->ligne_budgetaire_id !== (int) $ligne->id) {
                    $this->line("  Ligne {$ligne->code} → activité {$activite->code} (ligne_budgetaire_id à corriger)");
                    $stats['lignes']++;
                }
            } else {
                $sync->synchroniserLigne($ligne);
                $stats['lignes']++;
            }
        }

        $besoins = ExpressionBesoin::query()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('activite_id')
            ->whereNull('ligne_budgetaire_id')
            ->get();

        foreach ($besoins as $besoin) {
            $ligneId = Activite::find($besoin->activite_id)?->ligne_budgetaire_id;
            if (! $ligneId) {
                continue;
            }

            if ($dry) {
                $this->line("  Besoin {$besoin->numero} → ligne ID {$ligneId}");
                $stats['besoins']++;
            } else {
                $besoin->update(['ligne_budgetaire_id' => $ligneId]);
                $stats['besoins']++;
            }
        }

        $this->info('Réconciliation terminée.');
        $this->table(['Opération', 'Éléments traités'], [
            ['Activités synchronisées', $stats['activites']],
            ['Lignes synchronisées', $stats['lignes']],
            ['Besoins rattachés', $stats['besoins']],
        ]);

        return self::SUCCESS;
    }
}
