<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase R1 — socle « Gestion des Recettes » : catégories, prévisions et recettes.
 * Migration additive : ne modifie pas la table legacy `contributions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_recettes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle');
            $table->string('type', 40);
            $table->foreignId('parent_id')->nullable()->constrained('categories_recettes')->nullOnDelete();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'actif']);
        });

        Schema::create('previsions_recettes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->string('reference', 60);
            $table->foreignId('categorie_recette_id')->nullable()->constrained('categories_recettes')->nullOnDelete();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->nullOnDelete();
            $table->foreignId('etat_membre_id')->nullable()->constrained('etats_membres')->nullOnDelete();
            $table->foreignId('rubrique_id')->nullable()->constrained('budget_rubriques')->nullOnDelete();
            $table->foreignId('contribution_id')->nullable()->constrained('contributions')->nullOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('scenario', 30)->default('initial');
            $table->boolean('est_courante')->default(true);
            $table->decimal('montant_prevu', 18, 2)->default(0);
            $table->decimal('montant_revise', 18, 2)->nullable();
            $table->string('statut', 20)->default('brouillon');
            $table->text('observations')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exercice_id', 'reference']);
            $table->unique('contribution_id');
            $table->index(['exercice_id', 'statut', 'est_courante']);
            $table->index('categorie_recette_id');
        });

        Schema::create('recettes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('prevision_recette_id')->nullable()->constrained('previsions_recettes')->nullOnDelete();
            $table->string('reference', 60);
            $table->foreignId('categorie_recette_id')->nullable()->constrained('categories_recettes')->nullOnDelete();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->nullOnDelete();
            $table->foreignId('etat_membre_id')->nullable()->constrained('etats_membres')->nullOnDelete();
            $table->foreignId('rubrique_id')->nullable()->constrained('budget_rubriques')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->decimal('montant_prevu', 18, 2)->default(0);
            $table->decimal('montant_realise', 18, 2)->default(0);
            $table->string('statut', 30)->default('prevue');
            $table->date('date_prevue')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exercice_id', 'reference']);
            $table->unique('prevision_recette_id');
            $table->index(['exercice_id', 'statut']);
            $table->index('categorie_recette_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recettes');
        Schema::dropIfExists('previsions_recettes');
        Schema::dropIfExists('categories_recettes');
    }
};
