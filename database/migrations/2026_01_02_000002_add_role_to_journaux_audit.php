<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durcissement de la piste d'audit (Lot E) : fige le ou les rôle(s) de l'acteur au moment de l'action,
 * afin que la traçabilité résiste à toute modification ultérieure des habilitations.
 * NON DESTRUCTIF : colonne ajoutée seulement si absente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journaux_audit') && ! Schema::hasColumn('journaux_audit', 'role')) {
            Schema::table('journaux_audit', fn (Blueprint $t) => $t->string('role', 150)->nullable()->after('user_nom'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('journaux_audit') && Schema::hasColumn('journaux_audit', 'role')) {
            Schema::table('journaux_audit', fn (Blueprint $t) => $t->dropColumn('role'));
        }
    }
};
