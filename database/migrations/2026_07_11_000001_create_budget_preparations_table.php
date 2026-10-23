<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B2 — propositions budgétaires par structure / ligne. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_preparations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('ligne_budgetaire_id')->constrained('lignes_budgetaires')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();
            $table->string('reference', 60)->nullable();
            $table->decimal('montant_demande', 18, 2)->default(0);
            $table->decimal('montant_propose', 18, 2)->nullable();
            $table->decimal('montant_arbitre', 18, 2)->nullable();
            $table->enum('statut', [
                'brouillon', 'soumis_service', 'valide_direction', 'valide_departement', 'en_arbitrage', 'arbitre', 'consolide',
            ])->default('brouillon');
            $table->text('observations')->nullable();
            $table->timestamp('soumis_le')->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['budget_id', 'ligne_budgetaire_id'], 'budget_prep_ligne_unique');
            $table->index(['budget_id', 'structure_id'], 'budget_prep_structure_idx');
            $table->index(['budget_id', 'statut'], 'budget_prep_statut_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_preparations');
    }
};
