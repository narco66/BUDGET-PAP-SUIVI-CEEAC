<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Gestion des risques » (ISO 31000 / COSO — composante Évaluation des risques).
 * NON DESTRUCTIF : créations conditionnelles (hasTable). Registre central `risques` + référentiel
 * `categories_risques`. La cartographie agrège AUSSI les risques existants (stratégiques, PAP) en
 * lecture seule, sans les modifier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories_risques')) {
            Schema::create('categories_risques', function (Blueprint $t) {
                $t->id();
                $t->string('code', 30)->unique();
                $t->string('libelle');
                $t->text('description')->nullable();
                $t->unsignedInteger('ordre')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('risques')) {
            Schema::create('risques', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->foreignId('categorie_risque_id')->nullable()->constrained('categories_risques')->nullOnDelete();
                $t->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $t->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
                $t->foreignId('proprietaire_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('libelle');
                $t->text('description')->nullable();
                $t->string('processus', 120)->nullable();          // processus / domaine concerné
                $t->unsignedTinyInteger('probabilite')->default(1); // échelle 1 → 4
                $t->unsignedTinyInteger('impact')->default(1);      // échelle 1 → 4
                $t->text('mesures_maitrise')->nullable();
                $t->string('statut', 20)->default('identifie');     // identifie|en_traitement|maitrise|cloture
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['categorie_risque_id', 'statut']);
                $t->index('probabilite');
                $t->index('impact');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('risques');
        Schema::dropIfExists('categories_risques');
    }
};
