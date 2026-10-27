<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL — aligne les FK indicateurs.type_indicateur_id / unite_mesure_id sur MySQL (RESTRICT).
 * La migration 2026_06_23_000002 ne les recréait que sous MySQL : en PG elles restaient en SET NULL
 * sur des colonnes NOT NULL (suppression d'un type/unité utilisé → violation NOT NULL au lieu d'un refus).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->redefinir(fn ($fk) => $fk->restrictOnDelete());
    }

    public function down(): void
    {
        $this->redefinir(fn ($fk) => $fk->nullOnDelete());
    }

    private function redefinir(callable $regle): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('indicateurs', function (Blueprint $table) use ($regle) {
            $table->dropForeign(['type_indicateur_id']);
            $table->dropForeign(['unite_mesure_id']);
            $regle($table->foreign('type_indicateur_id')->references('id')->on('types_indicateurs'));
            $regle($table->foreign('unite_mesure_id')->references('id')->on('unites_mesure'));
        });
    }
};
