<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P2 — Pont explicite RBM ↔ Planification stratégique. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piliers', function (Blueprint $table) {
            $table->foreignId('orientation_strategique_id')
                ->nullable()
                ->after('pap_id')
                ->constrained('orientations_strategiques')
                ->nullOnDelete();
            $table->index('orientation_strategique_id', 'piliers_orientation_idx');
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->foreignId('projet_id')
                ->nullable()
                ->after('ligne_budgetaire_id')
                ->constrained('projets')
                ->nullOnDelete();
            $table->index('projet_id', 'activites_projet_idx');
        });
    }

    public function down(): void
    {
        Schema::table('activites', function (Blueprint $table) {
            $table->dropForeign(['projet_id']);
            $table->dropIndex('activites_projet_idx');
            $table->dropColumn('projet_id');
        });

        Schema::table('piliers', function (Blueprint $table) {
            $table->dropForeign(['orientation_strategique_id']);
            $table->dropIndex('piliers_orientation_idx');
            $table->dropColumn('orientation_strategique_id');
        });
    }
};
