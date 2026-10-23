<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Imputations multiples d'un engagement sur une ou plusieurs lignes budgétaires (EF-D5-01). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_imputations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignId('ligne_budgetaire_id')->constrained('lignes_budgetaires')->restrictOnDelete();
            $table->decimal('montant', 18, 2);
            $table->timestamps();

            $table->index('ligne_budgetaire_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_imputations');
    }
};
