<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Financement multi-sources par ligne budgétaire (Phase B). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ligne_source_financement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ligne_budgetaire_id')->constrained('lignes_budgetaires')->cascadeOnDelete();
            $table->foreignId('source_financement_id')->constrained('sources_financement')->cascadeOnDelete();
            $table->decimal('montant', 18, 2)->default(0);
            $table->decimal('pourcentage', 8, 4)->nullable();
            $table->boolean('est_principale')->default(false);
            $table->timestamps();

            $table->unique(['ligne_budgetaire_id', 'source_financement_id'], 'ligne_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ligne_source_financement');
    }
};
