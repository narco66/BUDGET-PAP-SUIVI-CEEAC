<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Budget\BudgetCompletenessService;
use Illuminate\Console\Command;

/** Phase B0 — rapprochements PAP, ventilations PTF et cohérence RBM ↔ budget. */
class BudgetCompleterDonneesCommand extends Command
{
    protected $signature = 'budget:completer-donnees
        {--exercice= : ID ou année de l\'exercice}
        {--dry-run : Simulation sans écriture}
        {--sans-rapprochement : Ne pas lancer le rapprochement PAP}
        {--sans-reconciliation : Ne pas synchroniser les liaisons RBM}
        {--sans-ventilations : Ne pas corriger les ventilations CEEAC/PTF}';

    protected $description = 'Complète les données budget (rapprochement PAP, ventilations PTF, liaisons RBM) — phase B0';

    public function handle(BudgetCompletenessService $service): int
    {
        $exerciceId = $this->resoudreExerciceId();
        abort_unless($exerciceId, self::FAILURE, 'Aucun exercice trouvé.');

        $exercice = Exercice::find($exerciceId);
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Complétion B0 — exercice {$exercice->annee} (ID {$exerciceId})");
        if ($dryRun) {
            $this->warn('Mode simulation — aucune modification en base.');
        }

        $stats = $service->completer($exerciceId, [
            'rapprocher' => ! $this->option('sans-rapprochement'),
            'reconcilier' => ! $this->option('sans-reconciliation'),
            'ventilations' => ! $this->option('sans-ventilations'),
            'dry_run' => $dryRun,
        ]);

        $this->table(
            ['Élément', 'Valeur'],
            collect($stats)
                ->except(['rapprochement', 'rbm_budget', 'referentiel_financier', 'succes'])
                ->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v])
                ->values()
                ->all()
        );

        if (isset($stats['rapprochement']) && $stats['rapprochement'] !== []) {
            $this->comment('Rapprochement PAP :');
            $this->table(
                ['Opération', 'Nombre'],
                collect($stats['rapprochement'])->map(fn ($v, $k) => [$k, $v])->values()->all()
            );
        }

        if ($dryRun) {
            $this->info('Simulation terminée.');

            return self::SUCCESS;
        }

        if ($stats['anomalies_total'] > 0) {
            $this->error("Anomalies restantes : {$stats['anomalies_total']} — exécutez budget:audit --json pour le détail.");

            return self::FAILURE;
        }

        $this->info('Complétion B0 terminée — aucune anomalie métier budget:audit.');

        return self::SUCCESS;
    }

    private function resoudreExerciceId(): ?int
    {
        if ($this->option('exercice')) {
            $opt = $this->option('exercice');

            return Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
        }

        return Exercice::firstWhere('statut', 'ouvert')?->id
            ?? Exercice::latest('annee')->value('id');
    }
}
