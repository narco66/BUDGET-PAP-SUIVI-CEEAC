<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Complète budgets.categorie_rejet si la table budgets est créée après la migration W-P0. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budgets') || Schema::hasColumn('budgets', 'categorie_rejet')) {
            return;
        }

        Schema::table('budgets', function (Blueprint $table) {
            $table->string('categorie_rejet', 30)->nullable()->after('motif_rejet');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('budgets') && Schema::hasColumn('budgets', 'categorie_rejet')) {
            Schema::table('budgets', fn (Blueprint $t) => $t->dropColumn('categorie_rejet'));
        }
    }
};
