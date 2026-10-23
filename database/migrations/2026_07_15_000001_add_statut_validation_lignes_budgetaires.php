<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 6 — Workflow de validation des lignes (brouillon → soumis → validé / rejeté).
 * NON DESTRUCTIF : défaut « valide » pour les lignes existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lignes_budgetaires')) {
            return;
        }

        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            if (! Schema::hasColumn('lignes_budgetaires', 'statut_validation')) {
                $table->string('statut_validation', 20)->default('valide')->after('actif');
            }
            if (! Schema::hasColumn('lignes_budgetaires', 'soumis_le')) {
                $table->timestamp('soumis_le')->nullable()->after('statut_validation');
            }
            if (! Schema::hasColumn('lignes_budgetaires', 'valide_le')) {
                $table->timestamp('valide_le')->nullable()->after('soumis_le');
            }
            if (! Schema::hasColumn('lignes_budgetaires', 'valide_par')) {
                $table->foreignId('valide_par')->nullable()->after('valide_le')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('lignes_budgetaires', 'motif_rejet')) {
                $table->text('motif_rejet')->nullable()->after('valide_par');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lignes_budgetaires')) {
            return;
        }

        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            if (Schema::hasColumn('lignes_budgetaires', 'motif_rejet')) {
                $table->dropColumn('motif_rejet');
            }
            if (Schema::hasColumn('lignes_budgetaires', 'valide_par')) {
                $table->dropConstrainedForeignId('valide_par');
            }
            if (Schema::hasColumn('lignes_budgetaires', 'valide_le')) {
                $table->dropColumn('valide_le');
            }
            if (Schema::hasColumn('lignes_budgetaires', 'soumis_le')) {
                $table->dropColumn('soumis_le');
            }
            if (Schema::hasColumn('lignes_budgetaires', 'statut_validation')) {
                $table->dropColumn('statut_validation');
            }
        });
    }
};
