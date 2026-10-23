<?php

namespace App\Console\Commands;

use Database\Seeders\SafeDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Génère/complète les données de démonstration de façon NON DESTRUCTIVE.
 *   php artisan budgetpap:seed-demo --safe
 * Refuse l'exécution en production sauf --force ; affiche un résumé avant/après ; journalise ; ne casse rien.
 */
class SeedDemoCommand extends Command
{
    protected $signature = 'budgetpap:seed-demo {--safe : Mode sûr (recommandé)} {--force : Autoriser en production}';

    protected $description = 'Complète la base avec des données de démonstration cohérentes, sans rien écraser.';

    /** Tables surveillées pour le rapport avant/après. */
    private array $tables = [
        'utilisateurs' => \App\Models\User::class,
        'structures' => \App\Models\Structure::class,
        'rôles' => \Spatie\Permission\Models\Role::class,
        'lignes budgétaires' => \App\Models\LigneBudgetaire::class,
        'activités PAP' => \App\Models\Activite::class,
        'indicateurs' => \App\Models\Indicateur::class,
        'expressions de besoin' => \App\Models\ExpressionBesoin::class,
        'engagements' => \App\Models\Engagement::class,
        'liquidations' => \App\Models\Liquidation::class,
        'ordonnancements' => \App\Models\Ordonnancement::class,
        'paiements' => \App\Models\Paiement::class,
        'campagnes de suivi' => \App\Models\CampagneSuivi::class,
        'collectes de suivi' => \App\Models\Collecte::class,
        'pièces justificatives' => \App\Models\PieceJustificative::class,
        'paramètres' => \App\Models\Parametre::class,
    ];

    public function handle(): int
    {
        $this->info('=== BUDGET-PAP-SUIVI-CEEAC · Génération de données de démonstration (non destructive) ===');

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Environnement de PRODUCTION détecté. Relancez avec --force pour confirmer (aucune donnée ne sera supprimée).');

            return self::FAILURE;
        }

        $avant = $this->compter();
        $this->afficherTable('État avant', $avant);

        if ($this->input->isInteractive() && ! $this->confirm('Compléter la base avec les données de démonstration ?', true)) {
            $this->warn('Opération annulée.');

            return self::SUCCESS;
        }

        $this->info('Insertion en cours (les enregistrements existants sont préservés)…');
        try {
            $this->callSilent('db:seed', ['--class' => SafeDatabaseSeeder::class, '--force' => true]);
        } catch (Throwable $e) {
            $this->error('Erreur durant le seeding : '.$e->getMessage());
            $this->warn('Les données existantes n’ont pas été altérées (insertions idempotentes).');

            return self::FAILURE;
        }

        $apres = $this->compter();
        $this->afficherRapport($avant, $apres);

        $total = \App\Models\LigneBudgetaire::sum('montant_ouvert');
        $engage = \App\Models\LigneBudgetaire::sum('montant_engage');
        $this->newLine();
        $this->info('Crédits ouverts : '.number_format((float) $total, 0, ',', ' ').' XAF · engagés : '.number_format((float) $engage, 0, ',', ' ').' XAF');
        $this->info('✔ Données de démonstration disponibles. Connexion : gestionnaire_budgetaire@ceeac.test / password');

        return self::SUCCESS;
    }

    private function compter(): array
    {
        $r = [];
        foreach ($this->tables as $label => $modele) {
            $r[$label] = (int) $modele::query()->count();
        }

        return $r;
    }

    private function afficherTable(string $titre, array $data): void
    {
        $this->newLine();
        $this->line("<comment>{$titre}</comment>");
        $this->table(['Donnée', 'Nombre'], collect($data)->map(fn ($n, $k) => [$k, $n])->values()->all());
    }

    private function afficherRapport(array $avant, array $apres): void
    {
        $rows = [];
        foreach ($apres as $label => $n) {
            $diff = $n - ($avant[$label] ?? 0);
            $rows[] = [$label, $avant[$label] ?? 0, $n, $diff >= 0 ? '+'.$diff : (string) $diff];
        }
        $this->newLine();
        $this->line('<info>Rapport final</info>');
        $this->table(['Donnée', 'Avant', 'Après', 'Ajoutés'], $rows);
    }
}
