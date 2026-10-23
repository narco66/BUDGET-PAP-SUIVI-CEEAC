<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P2 — plans d'amélioration et actions correctives S&E. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plans_amelioration')) {
            return;
        }

        Schema::create('plans_amelioration', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('evaluation_periodique_id')->nullable()->constrained('evaluations_periodiques')->nullOnDelete();
            $table->foreignId('mission_suivi_id')->nullable()->constrained('missions_suivi')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('intitule');
            $table->text('constat')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('echeance')->nullable();
            $table->enum('statut', ['brouillon', 'valide', 'en_cours', 'cloture', 'abandonne'])->default('brouillon');
            $table->decimal('taux_realisation', 5, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exercice_id', 'statut']);
        });

        Schema::create('actions_correctives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_amelioration_id')->constrained('plans_amelioration')->cascadeOnDelete();
            $table->foreignId('recommandation_id')->nullable()->constrained('recommandations')->nullOnDelete();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->date('echeance')->nullable();
            $table->enum('statut', ['en_attente', 'en_cours', 'realisee', 'non_realisee'])->default('en_attente');
            $table->decimal('taux', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['plan_amelioration_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions_correctives');
        Schema::dropIfExists('plans_amelioration');
    }
};
