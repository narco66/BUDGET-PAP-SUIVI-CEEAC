<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B3 — scénarios budgétaires comparables (what-if). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('libelle', 200);
            $table->text('hypothese')->nullable();
            $table->enum('type', ['global_pct', 'structure_pct', 'enveloppe_plafond', 'ligne_ajustements'])->default('global_pct');
            $table->json('parametres')->nullable();
            $table->json('resultat')->nullable();
            $table->decimal('montant_base', 18, 2)->default(0);
            $table->decimal('montant_simule', 18, 2)->default(0);
            $table->decimal('ecart_absolu', 18, 2)->default(0);
            $table->decimal('ecart_pct', 8, 4)->default(0);
            $table->enum('statut', ['brouillon', 'calcule', 'retenu', 'archive'])->default('brouillon');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calcule_le')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'code'], 'budget_sim_code_unique');
            $table->index(['budget_id', 'statut'], 'budget_sim_statut_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_simulations');
    }
};
