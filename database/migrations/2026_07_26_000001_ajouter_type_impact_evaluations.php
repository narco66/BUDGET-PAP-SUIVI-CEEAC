<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** P1 — ajoute le type « impact » aux évaluations S&E (non destructif, cross-DB via change()). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluations_periodiques')) {
            return;
        }

        Schema::table('evaluations_periodiques', function (Blueprint $table) {
            $table->enum('type', ['periodique', 'campagne', 'annuelle', 'impact'])
                ->default('periodique')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('evaluations_periodiques')) {
            return;
        }

        // Reclasse les évaluations d'impact avant de retirer la valeur de l'énum.
        DB::table('evaluations_periodiques')
            ->where('type', 'impact')
            ->update(['type' => 'annuelle']);

        Schema::table('evaluations_periodiques', function (Blueprint $table) {
            $table->enum('type', ['periodique', 'campagne', 'annuelle'])
                ->default('periodique')
                ->change();
        });
    }
};
