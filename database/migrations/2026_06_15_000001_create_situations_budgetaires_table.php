<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P1 Suivi Financier — snapshots de situation budgétaire consolidée. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('situations_budgetaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->date('date_situation');
            $table->decimal('budget_initial', 18, 2)->default(0);
            $table->decimal('budget_revise', 18, 2)->default(0);
            $table->decimal('montant_engage', 18, 2)->default(0);
            $table->decimal('montant_liquide', 18, 2)->default(0);
            $table->decimal('montant_ordonnance', 18, 2)->default(0);
            $table->decimal('montant_paye', 18, 2)->default(0);
            $table->decimal('credit_disponible', 18, 2)->default(0);
            $table->decimal('reste_a_engager', 18, 2)->default(0);
            $table->decimal('reste_a_payer', 18, 2)->default(0);
            $table->decimal('taux_engagement', 8, 2)->default(0);
            $table->decimal('taux_liquidation', 8, 2)->default(0);
            $table->decimal('taux_ordonnancement', 8, 2)->default(0);
            $table->decimal('taux_paiement', 8, 2)->default(0);
            $table->decimal('taux_execution', 8, 2)->default(0);
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['exercice_id', 'date_situation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('situations_budgetaires');
    }
};
