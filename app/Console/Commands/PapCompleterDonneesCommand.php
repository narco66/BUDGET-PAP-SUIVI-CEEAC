<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Pap\PapCompletenessService;
use Illuminate\Console\Command;

/** Phase P0 — complète budget, indicateurs, structures et responsables du PAP. */
class PapCompleterDonneesCommand extends Command
{
    protected $signature = 'pap:completer-donnees
        {--exercice= : ID ou année de l\'exercice}
        {--responsable= : ID utilisateur responsable par défaut}
        {--sans-agregats : Ne pas supprimer les lignes agrégées pilier}
        {--sans-rapprochement : Ne pas lancer le rapprochement final}';

    protected $description = 'Complète les données PAP (lignes budget, indicateurs, structures, responsables) — phase P0';

    public function handle(PapCompletenessService $service): int
    {
        $exerciceId = $this->resoudreExerciceId();
        abort_unless($exerciceId, self::FAILURE, 'Aucun exercice trouvé.');

        $exercice = Exercice::find($exerciceId);
        $this->info("Complétion P0 — exercice {$exercice->annee} (ID {$exerciceId})");

        $stats = $service->completer($exerciceId, [
            'remplacer_agregats' => ! $this->option('sans-agregats'),
            'rapprocher' => ! $this->option('sans-rapprochement'),
            'responsable_id' => $this->option('responsable') ? (int) $this->option('responsable') : null,
        ]);

        if (isset($stats['message'])) {
            $this->warn($stats['message']);

            return self::FAILURE;
        }

        $this->table(
            ['Élément', 'Nombre'],
            collect($stats)
                ->except(['rapprochement', 'succes', 'message'])
                ->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])
                ->values()
                ->all()
        );

        if ($stats['anomalies_critiques'] > 0) {
            $this->error("Anomalies critiques restantes : {$stats['anomalies_critiques']}");

            return self::FAILURE;
        }

        if ($stats['anomalies_total'] > 0) {
            $this->warn("Anomalies restantes : {$stats['anomalies_total']} — exécutez pap:audit pour le détail.");
        } else {
            $this->info('Complétion P0 terminée — aucune anomalie audit.');
        }

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
