<?php

namespace App\Console\Commands;

use App\Models\Exercice;
use App\Services\Pap\PapReplacementImportService;
use Illuminate\Console\Command;

class PapReplaceFromExcelCommand extends Command
{
    protected $signature = 'pap:replace-from-excel
                            {file : Chemin du fichier PAP Excel}
                            {--exercice= : ID ou annee de l\'exercice}
                            {--modele= : Chemin du modele officiel}
                            {--apply : Applique le remplacement complet}
                            {--user= : ID utilisateur a inscrire dans l\'historique}
                            {--json : Sortie JSON}';

    protected $description = 'Analyse ou remplace completement le PAP depuis un fichier Excel officiel';

    public function handle(PapReplacementImportService $import): int
    {
        $exercice = $this->resoudreExercice($this->option('exercice'));
        if (! $exercice) {
            $this->error('Exercice introuvable. Renseignez --exercice avec un ID ou une annee.');

            return self::FAILURE;
        }

        $file = (string) $this->argument('file');
        $modele = $this->option('modele') ? (string) $this->option('modele') : null;

        try {
            $analyse = $import->analyser($file, $modele);

            if (! $this->option('apply')) {
                return $this->sortieAnalyse($analyse, $exercice->id);
            }

            if (! $analyse['valide']) {
                return $this->sortieAnalyse($analyse, $exercice->id);
            }

            $resultat = $import->remplacer(
                $file,
                $exercice->id,
                $modele,
                $this->option('user') ? (int) $this->option('user') : null
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'exercice_id' => $exercice->id,
                    'analyse' => $analyse,
                    'resultat' => $resultat,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->info('Remplacement PAP applique.');
                $this->line('Exercice ID : '.$exercice->id);
                $this->line('Historique snapshot : #'.$resultat['historique_id']);
                $this->table(['Indicateur', 'Valeur'], collect($resultat['creation'])->map(
                    fn ($v, $k) => [$k, is_float($v) ? number_format($v, 2, ',', ' ') : $v]
                )->values()->all());
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->option('json')) {
                $this->line(json_encode([
                    'exercice_id' => $exercice->id,
                    'erreur' => $e->getMessage(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error($e->getMessage());
            }

            return self::FAILURE;
        }
    }

    private function sortieAnalyse(array $analyse, int $exerciceId): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'exercice_id' => $exerciceId,
                'mode' => 'audit',
                'analyse' => $analyse,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Audit fichier PAP');
            $this->line('Exercice ID : '.$exerciceId);
            $this->table(['Indicateur', 'Valeur'], collect($analyse['stats'])->map(
                fn ($v, $k) => [$k, is_float($v) ? number_format($v, 2, ',', ' ') : $v]
            )->values()->all());

            if ($analyse['ecarts_controle'] !== []) {
                $this->warn('Ecarts avec Rapport_controle :');
                foreach ($analyse['ecarts_controle'] as $label => $ecart) {
                    $this->line("  {$label} : attendu={$ecart['attendu']} calcule={$ecart['calcule']}");
                }
            }

            if (($analyse['corrections_codes'] ?? []) !== []) {
                $this->warn('Corrections automatiques de codes pour respecter les contraintes uniques :');
                foreach ($analyse['corrections_codes'] as $correction) {
                    $this->line("  Ligne {$correction['ligne_excel']} : {$correction['code_original']} -> {$correction['code_importe']}");
                }
            }

            if ($analyse['erreurs'] !== []) {
                $this->error('Erreurs bloquantes :');
                foreach ($analyse['erreurs'] as $erreur) {
                    $this->line('  - '.$erreur);
                }
            } else {
                $this->info('Fichier conforme. Relancez avec --apply pour appliquer le remplacement.');
            }

            if (($analyse['niveaux_absents_source']['taches'] ?? false) || ($analyse['niveaux_absents_source']['indicateurs'] ?? false)) {
                $this->warn('Le modele source ne contient pas de colonnes taches/indicateurs : ces niveaux ne seront pas crees par cet import.');
            }
        }

        return $analyse['valide'] ? self::SUCCESS : self::FAILURE;
    }

    private function resoudreExercice(mixed $option): ?Exercice
    {
        if ($option) {
            return Exercice::query()
                ->where('id', $option)
                ->orWhere('annee', $option)
                ->first();
        }

        return Exercice::query()->where('statut', 'ouvert')->first()
            ?? Exercice::query()->latest('annee')->first();
    }
}
