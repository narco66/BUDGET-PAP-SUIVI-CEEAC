<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Renforcement intégration nomenclature budgétaire (audit TB-PAPA-CEEAC). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lignes_budgetaires', 'montant_revise')) {
            Schema::table('lignes_budgetaires', function (Blueprint $table) {
                $table->decimal('montant_revise', 18, 2)->nullable()->after('montant_ouvert');
            });
        }

        DB::table('lignes_budgetaires')
            ->whereNull('montant_revise')
            ->update(['montant_revise' => DB::raw('montant_ouvert')]);

        if (! Schema::hasColumn('expressions_besoin', 'ligne_budgetaire_id')) {
            Schema::table('expressions_besoin', function (Blueprint $table) {
                $table->foreignId('ligne_budgetaire_id')
                    ->nullable()
                    ->after('activite_id')
                    ->constrained('lignes_budgetaires')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('expressions_besoin', 'ligne_budgetaire_id')) {
            Schema::table('expressions_besoin', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ligne_budgetaire_id');
            });
        }

        if (Schema::hasColumn('lignes_budgetaires', 'montant_revise')) {
            Schema::table('lignes_budgetaires', function (Blueprint $table) {
                $table->dropColumn('montant_revise');
            });
        }
    }
};
