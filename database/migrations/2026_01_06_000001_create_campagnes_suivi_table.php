<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Campagne de suivi-évaluation (D7 — EF-D7-01). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagnes_suivi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->string('libelle');
            $table->enum('type', ['mensuel', 'trimestriel', 'semestriel', 'annuel', 'pluriannuel'])->default('trimestriel');
            $table->string('periode', 20); // ex: 2026-T1
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->enum('statut', ['ouverte', 'cloturee'])->default('ouverte');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exercice_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campagnes_suivi');
    }
};
