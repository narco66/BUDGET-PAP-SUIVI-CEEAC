<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Depense\DepenseAuditService;
use Illuminate\Console\Command;

/** Audit complet chaîne de la dépense (phases D3–D5). */
class DepenseAuditCommand extends Command
{
    protected $signature = 'depense:audit
        {--exercice= : ID ou année de l\'exercice}
        {--json : Sortie JSON}
        {--persister : Enregistrer le snapshot dans audit_depenses}
        {--seuil=elevee : Seuil d\'échec : elevee, moyenne ou faible}
        {--detail : Afficher toutes les anomalies}';

    protected $description = 'Audit de cohérence Besoin → Engagement → Liquidation → Ordonnancement → Paiement (compteurs, comptabilité, crédits)';

    public function handle(DepenseAuditService $audit): int
    {
        $exerciceId = null;
        if ($this->option('exercice')) {
            $opt = $this->option('exercice');
            $exerciceId = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
            if (! $exerciceId) {
                $this->error('Exercice introuvable.');

                return self::FAILURE;
            }
        }

        $rapport = $audit->executer($exerciceId, null, (bool) $this->option('persister'));
        $seuil = (string) $this->option('seuil');

        if ($this->option('json')) {
            $this->line(json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $audit->depasseSeuil($rapport['anomalies'] ?? [], $seuil) ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Audit chaîne de la dépense — CEEAC');
        $this->line('Exercice : '.($exerciceId ? "ID {$exerciceId}" : 'tous'));
        $this->line('Score conformité : '.($rapport['score_conformite'] ?? 0).'/100');
        $this->newLine();

        $this->comment('SYNTHÈSE');
        foreach ((array) ($rapport['synthese'] ?? []) as $k => $v) {
            $this->line("  {$k} : {$v}");
        }
        $this->newLine();

        $this->comment('SECTIONS');
        foreach ((array) ($rapport['sections'] ?? []) as $section => $count) {
            $this->line("  {$section} : {$count} anomalie(s)");
        }
        $this->newLine();

        $this->comment('PAR GRAVITÉ');
        foreach ((array) ($rapport['par_gravite'] ?? []) as $g => $n) {
            $this->line("  {$g} : {$n}");
        }
        $this->newLine();

        $anomalies = $rapport['anomalies'] ?? [];
        $limite = $this->option('detail') ? count($anomalies) : 25;
        $this->comment('ANOMALIES ('.count($anomalies).')');
        foreach (array_slice($anomalies, 0, $limite) as $a) {
            $this->warn(sprintf(
                '  · [%s] %s — %s',
                $a['gravite'] ?? '?',
                $a['type'] ?? '?',
                $a['numero'] ?? ''
            ));
        }
        if (! $this->option('detail') && count($anomalies) > $limite) {
            $this->line('  … '.(count($anomalies) - $limite).' autre(s) — utilisez --detail');
        }

        $this->newLine();
        $this->info('Total anomalies : '.count($anomalies));

        return $audit->depasseSeuil($anomalies, $seuil) ? self::FAILURE : self::SUCCESS;
    }
}
