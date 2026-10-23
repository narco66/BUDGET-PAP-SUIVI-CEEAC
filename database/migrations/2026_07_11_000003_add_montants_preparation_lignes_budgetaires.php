<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B2 — montants demandé / proposé sur les lignes budgétaires. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            $table->decimal('montant_demande', 18, 2)->nullable()->after('montant_ouvert');
            $table->decimal('montant_propose', 18, 2)->nullable()->after('montant_demande');
        });
    }

    public function down(): void
    {
        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            $table->dropColumn(['montant_demande', 'montant_propose']);
        });
    }
};
