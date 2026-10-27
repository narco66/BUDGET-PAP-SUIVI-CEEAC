<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ligne PAP peut faire l'objet de plusieurs activités (expressions de besoin) : les tâches saisies lors
 * d'une expression de besoin lui sont rattachées. ADDITIVE : colonne nullable, aucune donnée modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taches', function (Blueprint $table) {
            $table->foreignId('expression_besoin_id')->nullable()->after('activite_id')
                ->constrained('expressions_besoin')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('taches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expression_besoin_id');
        });
    }
};
