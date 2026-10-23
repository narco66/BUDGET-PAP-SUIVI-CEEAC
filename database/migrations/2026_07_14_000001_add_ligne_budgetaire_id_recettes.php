<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lot 5 — lien optionnel recettes / prévisions ↔ ligne budgétaire (non destructif). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('previsions_recettes') && ! Schema::hasColumn('previsions_recettes', 'ligne_budgetaire_id')) {
            Schema::table('previsions_recettes', function (Blueprint $table) {
                $table->foreignId('ligne_budgetaire_id')->nullable()->after('rubrique_id')
                    ->constrained('lignes_budgetaires')->nullOnDelete();
            });
        }

        if (Schema::hasTable('recettes') && ! Schema::hasColumn('recettes', 'ligne_budgetaire_id')) {
            Schema::table('recettes', function (Blueprint $table) {
                $table->foreignId('ligne_budgetaire_id')->nullable()->after('rubrique_id')
                    ->constrained('lignes_budgetaires')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recettes', 'ligne_budgetaire_id')) {
            Schema::table('recettes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ligne_budgetaire_id');
            });
        }

        if (Schema::hasColumn('previsions_recettes', 'ligne_budgetaire_id')) {
            Schema::table('previsions_recettes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ligne_budgetaire_id');
            });
        }
    }
};
