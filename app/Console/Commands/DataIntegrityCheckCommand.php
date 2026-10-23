<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Audit\DataIntegrityCheckService;
use Illuminate\Console\Command;

/** Contrôle d'intégrité global des données — agrège les audits métier (P2-10). */
class DataIntegrityCheckCommand extends Command
{
    protected $signature = 'data:integrity-check
                            {--exercice= : ID ou année de l\'exercice}
                            {--module=* : Module(s) à exécuter (transversal,organisation,pap,budget,rbm,depense,suivi,compteurs)}
                            {--json : Sortie JSON}
                            {--persister : Enregistrer le snapshot dans audit_integrite_data}
                            {--seuil=warning : Seuil d\'échec : info, warning ou critique}
                            {--detail : Afficher toutes les anomalies}';

    protected $description = 'Contrôle d\'intégrité transversal (organisation, PAP, budget, RBM, dépense, suivi, compteurs)';

    public function handle(DataIntegrityCheckService $integrity): int
    {
        if (! config('audit.integrity.enabled', true)) {
            $this->warn('Contrôle d\'intégrité désactivé (DATA_INTEGRITY_ENABLED=false).');

            return self::SUCCESS;
        }

        $exerciceId = $this->resoudreExercice();
        if ($exerciceId === false) {
            return self::FAILURE;
        }
        $modules = $this->option('module') ?: null;

        $rapport = $integrity->executer(
            $exerciceId,
            $modules !== [] ? $modules : null,
            (bool) $this->option('persister'),
        );

        $seuil = (string) $this->option('seuil');
        $anomalies = $rapport['anomalies'] ?? [];

        if ($this->option('json')) {
            $this->line(json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $integrity->depasseSeuil($anomalies, $seuil) ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Contrôle d\'intégrité des données — CEEAC');
        $this->line('Exercice : '.($exerciceId ? "ID {$exerciceId}" : 'non ciblé (modules globaux uniquement)'));
        $this->line('Score conformité : '.($rapport['synthese']['score_conformite'] ?? 0).'/100');
        $this->newLine();

        $this->comment('MODULES');
        foreach ((array) ($rapport['modules'] ?? []) as $nom => $stats) {
            $total = $stats['anomalies_total'] ?? 0;
            $score = $stats['score'] ?? '-';
            $line = "  {$nom} : {$total} anomalie(s), score {$score}";
            $total > 0 ? $this->warn($line) : $this->line($line);
        }
        $this->newLine();

        $limite = $this->option('detail') ? count($anomalies) : 30;
        $this->comment('ANOMALIES ('.count($anomalies).')');
        foreach (array_slice($anomalies, 0, $limite) as $a) {
            $line = sprintf('  · [%s/%s] %s — %s', $a['module'] ?? '?', $a['niveau'] ?? '?', $a['code'] ?? '?', $a['message'] ?? '');
            match ($a['niveau'] ?? 'warning') {
                'critique' => $this->error($line),
                'warning' => $this->warn($line),
                default => $this->line($line),
            };
        }
        if (! $this->option('detail') && count($anomalies) > $limite) {
            $this->line('  … '.(count($anomalies) - $limite).' autre(s) — utilisez --detail');
        }

        $this->newLine();
        $this->info('Total : '.count($anomalies).' anomalie(s), dont '.($rapport['synthese']['anomalies_critiques'] ?? 0).' critique(s).');

        if ($this->option('persister')) {
            $this->comment('Snapshot enregistré dans audit_integrite_data.');
        }

        return $integrity->depasseSeuil($anomalies, $seuil) ? self::FAILURE : self::SUCCESS;
    }

    private function resoudreExercice(): int|false|null
    {
        if ($this->option('exercice')) {
            $opt = $this->option('exercice');
            $id = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
            if (! $id) {
                $this->error('Exercice introuvable.');

                return false;
            }

            return (int) $id;
        }

        return Exercice::firstWhere('statut', 'ouvert')?->id
            ?? Exercice::latest('annee')->value('id');
    }
}
