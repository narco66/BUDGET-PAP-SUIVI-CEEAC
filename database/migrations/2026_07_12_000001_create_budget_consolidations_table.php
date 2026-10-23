<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B3 — snapshots persistés des consolidations budgétaires. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_consolidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('reference', 60);
            $table->unsignedSmallInteger('version')->default(1);
            $table->json('snapshot')->nullable();
            $table->json('resultat')->nullable();
            $table->unsignedInteger('lignes_count')->default(0);
            $table->decimal('montant_total', 18, 2)->default(0);
            $table->enum('statut', ['brouillon', 'applique', 'annule'])->default('applique');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['budget_id', 'version'], 'budget_consol_version_unique');
            $table->index(['budget_id', 'statut'], 'budget_consol_statut_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_consolidations');
    }
};
