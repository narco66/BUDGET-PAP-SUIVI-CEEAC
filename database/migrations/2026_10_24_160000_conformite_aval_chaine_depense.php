<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Conformité ENG→LIQ→ORD (procédures 24/10/2026) — additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordonnancements', function (Blueprint $table) {
            if (! Schema::hasColumn('ordonnancements', 'seuil_xaf')) {
                $table->decimal('seuil_xaf', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('ordonnancements', 'seuil_version')) {
                $table->unsignedInteger('seuil_version')->nullable();
            }
            if (! Schema::hasColumn('ordonnancements', 'assiette_seuil')) {
                $table->string('assiette_seuil', 20)->nullable();
            }
            if (! Schema::hasColumn('ordonnancements', 'ordonnateur_role')) {
                $table->string('ordonnateur_role', 50)->nullable();
            }
        });

        Schema::table('paiements', function (Blueprint $table) {
            if (! Schema::hasColumn('paiements', 'etat_execution')) {
                $table->string('etat_execution', 30)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordonnancements', function (Blueprint $table) {
            foreach (['seuil_xaf', 'seuil_version', 'assiette_seuil', 'ordonnateur_role'] as $col) {
                if (Schema::hasColumn('ordonnancements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('paiements', function (Blueprint $table) {
            if (Schema::hasColumn('paiements', 'etat_execution')) {
                $table->dropColumn('etat_execution');
            }
        });
    }
};
