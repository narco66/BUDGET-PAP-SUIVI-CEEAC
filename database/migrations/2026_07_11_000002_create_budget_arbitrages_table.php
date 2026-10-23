<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B2 — historique des arbitrages DPPB. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_arbitrages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('budget_preparation_id')->nullable()->constrained('budget_preparations')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 40);
            $table->decimal('montant_avant', 18, 2)->default(0);
            $table->decimal('montant_apres', 18, 2)->default(0);
            $table->text('motif')->nullable();
            $table->timestamps();

            $table->index(['budget_id', 'created_at'], 'budget_arbitrages_budget_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_arbitrages');
    }
};
