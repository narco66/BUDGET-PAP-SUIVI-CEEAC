<?php



namespace App\Console\Commands;



use App\Models\Exercice;

use App\Services\Budget\PapBudgetRapprochementService;

use Illuminate\Console\Command;



/** Rapproche les activités PAP et les lignes budgétaires (post-import). */

class BudgetRapprocherPapCommand extends Command

{

    protected $signature = 'budget:rapprocher-pap

                            {--exercice= : ID ou année de l\'exercice (requis)}

                            {--dry-run : Simulation sans écriture}

                            {--sans-lier : Ne pas créer les liaisons par code}

                            {--aligner-montants : Aligner montant_ouvert sur montant PAP (sans engagement)}

                            {--json : Sortie JSON}';



    protected $description = 'Rapprochement PAP ↔ lignes budgétaires (liaisons et écarts de montants)';



    public function handle(PapBudgetRapprochementService $rapprochement): int

    {

        $opt = $this->option('exercice');

        if (! $opt) {

            $this->error('Option --exercice requise (ID ou année).');



            return self::FAILURE;

        }



        $exerciceId = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');

        if (! $exerciceId) {

            $this->error("Exercice introuvable : {$opt}");



            return self::FAILURE;

        }



        $analyse = $rapprochement->analyser($exerciceId);



        if ($this->option('json') && ! $this->option('aligner-montants') && ! $this->option('sans-lier')) {

            $this->line(json_encode($analyse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));



            return ($analyse['ecarts_total'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;

        }



        $this->info('Rapprochement PAP ↔ lignes budgétaires');

        $this->line("Exercice ID : {$exerciceId}");

        $this->table(

            ['Indicateur', 'Valeur'],

            collect($analyse['synthese'])->map(fn ($v, $k) => [$k, is_float($v) ? number_format($v, 0, ',', ' ') : $v])->values()->all()

        );



        if ($analyse['ecarts_total'] > 0) {

            $this->newLine();

            $this->comment('Écarts détectés :');

            foreach ($analyse['ecarts_par_type'] as $type => $count) {

                $this->line("  {$type} : {$count}");

            }

            foreach (array_slice($analyse['ecarts'], 0, 15) as $ecart) {

                $detail = $ecart['activite_code'];

                if ($ecart['ligne_code'] ?? null) {

                    $detail .= " ↔ {$ecart['ligne_code']}";

                }

                if (isset($ecart['ecart'])) {

                    $detail .= ' (Δ '.number_format($ecart['ecart'], 0, ',', ' ').')';

                }

                $this->warn("  · [{$ecart['type']}] {$detail}");

            }

            if ($analyse['ecarts_total'] > 15) {

                $this->line('  … '.($analyse['ecarts_total'] - 15).' autre(s)');

            }

        } else {

            $this->info('Aucun écart détecté.');

        }



        $dryRun = (bool) $this->option('dry-run');

        $executer = $this->option('aligner-montants') || ! $this->option('sans-lier');



        if (! $executer) {

            return ($analyse['ecarts_total'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;

        }



        if ($dryRun) {

            $this->warn('Mode simulation — aucune modification en base.');

        }



        $stats = $rapprochement->rapprocher($exerciceId, [

            'lier_codes' => ! $this->option('sans-lier'),

            'aligner_montants' => (bool) $this->option('aligner-montants'),

            'dry_run' => $dryRun,

        ]);



        $this->newLine();

        $this->info('Opérations '.($dryRun ? 'simulées' : 'appliquées').' :');

        $this->table(['Opération', 'Nombre'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());



        if (! $dryRun) {

            $apres = $rapprochement->analyser($exerciceId);

            $this->info("Écarts restants : {$apres['ecarts_total']}");



            return $apres['ecarts_total'] > 0 ? self::FAILURE : self::SUCCESS;

        }



        return self::SUCCESS;

    }

}

