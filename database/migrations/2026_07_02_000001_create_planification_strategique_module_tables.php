<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase P0 — Module Planification Stratégique (socle de données).
 * Tables distinctes du référentiel institutionnel `programmes` (structure/pilier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('intitule');
            $table->string('periode', 50)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->string('statut', 20)->default('brouillon');
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->string('document_officiel')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['statut', 'version']);
        });

        Schema::create('plans_strategiques', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('intitule');
            $table->foreignId('vision_id')->nullable()->constrained('visions')->nullOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('statut', 20)->default('brouillon');
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->string('document_officiel')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['vision_id', 'statut']);
            $table->index(['date_debut', 'date_fin']);
        });

        Schema::create('orientations_strategiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_strategique_id')->constrained('plans_strategiques')->cascadeOnDelete();
            $table->foreignId('vision_id')->nullable()->constrained('visions')->nullOnDelete();
            $table->string('code', 30);
            $table->string('intitule');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->text('description')->nullable();
            $table->string('statut', 20)->default('actif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['plan_strategique_id', 'code']);
            $table->index(['vision_id', 'ordre']);
        });

        Schema::create('programmes_strategiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orientation_strategique_id')->constrained('orientations_strategiques')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('intitule');
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->decimal('budget_previsionnel', 18, 2)->default(0);
            $table->text('objectifs')->nullable();
            $table->text('resultats_attendus')->nullable();
            $table->string('statut', 20)->default('brouillon');
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->foreignId('pilier_id')->nullable()->constrained('piliers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['orientation_strategique_id', 'code']);
            $table->index(['statut', 'responsable_user_id']);
        });

        Schema::create('projets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_strategique_id')->constrained('programmes_strategiques')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('intitule');
            $table->foreignId('responsable_user_id')->constrained('users')->restrictOnDelete();
            $table->string('statut', 20)->default('planifie');
            $table->string('cycle_vie', 30)->default('initiation');
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->decimal('budget_previsionnel', 18, 2)->default(0);
            $table->decimal('budget_engage', 18, 2)->default(0);
            $table->decimal('budget_consomme', 18, 2)->default(0);
            $table->foreignId('pilier_id')->nullable()->constrained('piliers')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['programme_strategique_id', 'code']);
            $table->index(['statut', 'cycle_vie']);
            $table->index('responsable_user_id');
        });

        Schema::create('portefeuilles_projets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('intitule');
            $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut', 20)->default('actif');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['exercice_id', 'statut']);
        });

        Schema::create('portefeuille_projet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portefeuille_projet_id')->constrained('portefeuilles_projets')->cascadeOnDelete();
            $table->foreignId('projet_id')->constrained('projets')->cascadeOnDelete();
            $table->unsignedSmallInteger('priorite')->default(0);
            $table->string('categorie', 50)->nullable();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->nullOnDelete();
            $table->timestamps();
            $table->unique(['portefeuille_projet_id', 'projet_id']);
            $table->index(['priorite', 'categorie']);
        });

        Schema::create('programmations_pluriannuelles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_strategique_id')->constrained('plans_strategiques')->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->unsignedSmallInteger('annee_debut');
            $table->unsignedSmallInteger('annee_fin');
            $table->string('statut', 20)->default('brouillon');
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->json('scenarios')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['plan_strategique_id', 'annee_debut', 'annee_fin'], 'prog_pluri_plan_annees_idx');
        });

        Schema::create('programmations_annuelles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programmation_pluriannuelle_id')->constrained('programmations_pluriannuelles')->restrictOnDelete();
            $table->foreignId('exercice_id')->constrained('exercices')->restrictOnDelete();
            $table->string('statut', 20)->default('brouillon');
            $table->text('activites_prevues')->nullable();
            $table->text('ressources_prevues')->nullable();
            $table->text('indicateurs_prevus')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['programmation_pluriannuelle_id', 'exercice_id'], 'prog_ann_pluri_exercice_uniq');
            $table->index('statut');
        });

        Schema::create('objectifs_strategiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_strategique_id')->constrained('plans_strategiques')->cascadeOnDelete();
            $table->foreignId('orientation_strategique_id')->nullable()->constrained('orientations_strategiques')->nullOnDelete();
            $table->string('code', 30);
            $table->string('intitule');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->string('statut', 20)->default('actif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['plan_strategique_id', 'code']);
        });

        Schema::create('indicateurs_strategiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objectif_strategique_id')->constrained('objectifs_strategiques')->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('libelle');
            $table->string('unite', 50)->nullable();
            $table->string('periodicite', 20)->default('annuel');
            $table->decimal('valeur_reference', 18, 4)->nullable();
            $table->decimal('valeur_cible', 18, 4)->nullable();
            $table->decimal('seuil_alerte', 18, 4)->nullable();
            $table->foreignId('indicateur_id')->nullable()->constrained('indicateurs')->nullOnDelete();
            $table->string('statut', 20)->default('actif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['objectif_strategique_id', 'statut'], 'ind_strat_obj_statut_idx');
        });

        Schema::create('risques_strategiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_strategique_id')->constrained('plans_strategiques')->cascadeOnDelete();
            $table->foreignId('projet_id')->nullable()->constrained('projets')->nullOnDelete();
            $table->string('code', 30);
            $table->text('description');
            $table->string('probabilite', 20)->default('moyenne');
            $table->string('impact', 20)->default('moyen');
            $table->text('mitigation')->nullable();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut', 20)->default('identifie');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['plan_strategique_id', 'code']);
        });

        Schema::create('alignements_strategiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vision_id')->nullable()->constrained('visions')->nullOnDelete();
            $table->foreignId('orientation_strategique_id')->nullable()->constrained('orientations_strategiques')->nullOnDelete();
            $table->foreignId('plan_strategique_id')->nullable()->constrained('plans_strategiques')->nullOnDelete();
            $table->string('alignable_type');
            $table->unsignedBigInteger('alignable_id');
            $table->string('niveau_alignement', 30)->default('direct');
            $table->text('commentaire')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['alignable_type', 'alignable_id']);
            $table->index(['vision_id', 'plan_strategique_id'], 'align_vision_plan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alignements_strategiques');
        Schema::dropIfExists('risques_strategiques');
        Schema::dropIfExists('indicateurs_strategiques');
        Schema::dropIfExists('objectifs_strategiques');
        Schema::dropIfExists('programmations_annuelles');
        Schema::dropIfExists('programmations_pluriannuelles');
        Schema::dropIfExists('portefeuille_projet');
        Schema::dropIfExists('portefeuilles_projets');
        Schema::dropIfExists('projets');
        Schema::dropIfExists('programmes_strategiques');
        Schema::dropIfExists('orientations_strategiques');
        Schema::dropIfExists('plans_strategiques');
        Schema::dropIfExists('visions');
    }
};
