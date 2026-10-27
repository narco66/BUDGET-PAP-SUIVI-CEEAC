<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Élargit journaux_audit.action (30 → 64) : plusieurs actions métier dépassent 30 caractères
 * (engagement_insuffisance_credits, ordonnancement_alerte_fractionnement, structure_budget_ligne_*).
 * SQLite n'applique pas la longueur ; MySQL strict et PostgreSQL rejettent l'insertion, et sous
 * PostgreSQL l'erreur interrompt la transaction métier englobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journaux_audit', function (Blueprint $table) {
            $table->string('action', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('journaux_audit', function (Blueprint $table) {
            $table->string('action', 30)->change();
        });
    }
};
