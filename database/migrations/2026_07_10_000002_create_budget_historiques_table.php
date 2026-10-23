<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B1 — historisation du cycle de vie budget (soumissions, adoption, révisions). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_historiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('action', 40);
            $table->json('snapshot')->nullable();
            $table->boolean('snapshot_complet')->default(false);
            $table->text('motif')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['budget_id', 'version'], 'budget_historiques_budget_version_idx');
            $table->index(['budget_id', 'action'], 'budget_historiques_budget_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_historiques');
    }
};
