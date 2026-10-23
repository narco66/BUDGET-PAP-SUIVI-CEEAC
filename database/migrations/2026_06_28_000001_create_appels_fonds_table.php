<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase R4 — appels de fonds et suivi du recouvrement des contributions
 * (États membres et PTF) : montant appelé, réglé, échéance, arriéré, pénalités.
 * Migration additive : ne modifie pas la table legacy `contributions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appels_fonds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('etat_membre_id')->nullable()->constrained('etats_membres')->nullOnDelete();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->nullOnDelete();
            $table->foreignId('contribution_id')->nullable()->constrained('contributions')->nullOnDelete();
            $table->string('reference', 60)->unique();
            $table->string('objet');
            $table->decimal('montant_appele', 18, 2)->default(0);
            $table->decimal('montant_regle', 18, 2)->default(0);
            $table->date('date_emission');
            $table->date('date_echeance')->nullable();
            $table->decimal('taux_penalite', 5, 2)->default(0);
            $table->string('statut', 30)->default('emis');
            $table->text('observations')->nullable();
            $table->foreignId('emis_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exercice_id', 'etat_membre_id']);
            $table->index(['statut', 'date_echeance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appels_fonds');
    }
};
