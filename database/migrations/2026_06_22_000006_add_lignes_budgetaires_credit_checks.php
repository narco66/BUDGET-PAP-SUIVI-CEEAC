<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phase C — contraintes d'intégrité crédits sur lignes budgétaires (MySQL production). */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        $coalesce = $driver === 'pgsql' ? 'COALESCE' : 'IFNULL';

        DB::statement('ALTER TABLE lignes_budgetaires ADD CONSTRAINT chk_lb_montants_positifs CHECK (
            montant_ouvert >= 0 AND montant_engage >= 0 AND montant_liquide >= 0
            AND montant_ordonnance >= 0 AND montant_paye >= 0
            AND (montant_revise IS NULL OR montant_revise >= 0)
        )');

        DB::statement("ALTER TABLE lignes_budgetaires ADD CONSTRAINT chk_lb_chaine_credits CHECK (
            montant_engage <= {$coalesce}(montant_revise, montant_ouvert)
            AND montant_liquide <= montant_engage
            AND montant_ordonnance <= montant_liquide
            AND montant_paye <= montant_ordonnance
        )");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE lignes_budgetaires DROP CONSTRAINT IF EXISTS chk_lb_montants_positifs');
            DB::statement('ALTER TABLE lignes_budgetaires DROP CONSTRAINT IF EXISTS chk_lb_chaine_credits');

            return;
        }

        if ($driver !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE lignes_budgetaires DROP CHECK chk_lb_montants_positifs');
        DB::statement('ALTER TABLE lignes_budgetaires DROP CHECK chk_lb_chaine_credits');
    }
};
