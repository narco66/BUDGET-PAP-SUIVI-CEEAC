<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B1 — rattachement des lignes à l'enveloppe adoptée. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('exercice_id')->constrained('budgets')->nullOnDelete();
            $table->index('budget_id');
        });
    }

    public function down(): void
    {
        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_id');
        });
    }
};
