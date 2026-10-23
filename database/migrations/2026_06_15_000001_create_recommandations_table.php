<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Suivi des recommandations (Conférence des Chefs d'État, Conseil des Ministres, réunions, audits). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recommandations')) {
            return;
        }

        Schema::create('recommandations', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('source', ['conference_chefs_etat', 'conseil_ministres', 'reunion_statutaire', 'mission_audit', 'autre'])->default('autre');
            $table->string('reference_reunion')->nullable();
            $table->date('date_emission')->nullable();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->enum('priorite', ['haute', 'moyenne', 'basse'])->default('moyenne');
            $table->date('echeance')->nullable();
            $table->enum('statut', ['en_attente', 'en_cours', 'realisee', 'non_realisee', 'abandonnee'])->default('en_attente');
            $table->decimal('taux_execution', 5, 2)->default(0);
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exercice_id', 'source', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommandations');
    }
};
