<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P2 — missions de suivi-évaluation institutionnelles. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('missions_suivi')) {
            return;
        }

        Schema::create('missions_suivi', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('evaluation_periodique_id')->nullable()->constrained('evaluations_periodiques')->nullOnDelete();
            $table->enum('type', ['suivi', 'evaluation', 'audit', 'controle'])->default('suivi');
            $table->string('libelle');
            $table->text('objectif')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->enum('statut', ['planifiee', 'en_cours', 'cloturee', 'annulee'])->default('planifiee');
            $table->decimal('score_mission', 5, 2)->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exercice_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions_suivi');
    }
};
