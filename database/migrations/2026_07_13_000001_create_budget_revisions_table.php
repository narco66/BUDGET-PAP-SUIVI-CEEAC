<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B4 — rectificatifs et virements budgétaires tracés. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('reference', 60);
            $table->enum('type', ['rectificatif', 'virement'])->default('rectificatif');
            $table->text('motif');
            $table->decimal('montant_total', 18, 2)->default(0);
            $table->enum('statut', ['brouillon', 'valide', 'annule'])->default('brouillon');
            $table->foreignId('programmation_pluriannuelle_id')->nullable()->constrained('programmations_pluriannuelles')->nullOnDelete();
            $table->foreignId('programmation_annuelle_id')->nullable()->constrained('programmations_annuelles')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['budget_id', 'statut'], 'budget_rev_statut_idx');
            $table->index(['budget_id', 'type'], 'budget_rev_type_idx');
        });

        Schema::create('budget_revision_mouvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_revision_id')->constrained('budget_revisions')->cascadeOnDelete();
            $table->foreignId('ligne_budgetaire_id')->constrained('lignes_budgetaires')->restrictOnDelete();
            $table->foreignId('ligne_destination_id')->nullable()->constrained('lignes_budgetaires')->nullOnDelete();
            $table->enum('role', ['cible', 'source', 'destination'])->default('cible');
            $table->decimal('montant_avant', 18, 2)->default(0);
            $table->decimal('montant_apres', 18, 2)->default(0);
            $table->decimal('delta', 18, 2)->default(0);
            $table->timestamps();

            $table->index('budget_revision_id', 'budget_rev_mvt_rev_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_revision_mouvements');
        Schema::dropIfExists('budget_revisions');
    }
};
