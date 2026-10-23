<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B4 — lien budget ↔ programmation pluriannuelle. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->foreignId('programmation_pluriannuelle_id')->nullable()->after('montant_total')
                ->constrained('programmations_pluriannuelles')->nullOnDelete();
            $table->foreignId('programmation_annuelle_id')->nullable()->after('programmation_pluriannuelle_id')
                ->constrained('programmations_annuelles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('programmation_annuelle_id');
            $table->dropConstrainedForeignId('programmation_pluriannuelle_id');
        });
    }
};
