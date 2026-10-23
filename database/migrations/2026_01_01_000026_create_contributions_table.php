<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Contribution statutaire d'un État membre (ou appui PTF) par exercice. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('etat_membre_id')->nullable()->constrained('etats_membres')->nullOnDelete();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->nullOnDelete();
            $table->decimal('montant_appele', 18, 2)->default(0);
            $table->decimal('montant_realise', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['exercice_id', 'etat_membre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
