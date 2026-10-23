<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P0 — catégorisation et lien optionnel vers une évaluation périodique. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommandations', function (Blueprint $table) {
            if (! Schema::hasColumn('recommandations', 'categorie')) {
                $table->enum('categorie', [
                    'gouvernance', 'technique', 'financier', 'rh', 'institutionnel', 'autre',
                ])->default('autre')->after('source');
            }

            if (! Schema::hasColumn('recommandations', 'evaluation_periodique_id')) {
                $table->foreignId('evaluation_periodique_id')
                    ->nullable()
                    ->after('exercice_id')
                    ->constrained('evaluations_periodiques')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('recommandations', function (Blueprint $table) {
            if (Schema::hasColumn('recommandations', 'evaluation_periodique_id')) {
                $table->dropConstrainedForeignId('evaluation_periodique_id');
            }
            if (Schema::hasColumn('recommandations', 'categorie')) {
                $table->dropColumn('categorie');
            }
        });
    }
};
