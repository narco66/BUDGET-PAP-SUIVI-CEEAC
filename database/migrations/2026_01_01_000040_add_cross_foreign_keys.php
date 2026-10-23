<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les FK croisées une fois toutes les tables créées,
 * pour lever les dépendances circulaires du modèle.
 * SQLite ne sait pas ajouter de FK via ALTER TABLE : on l'ignore (colonnes déjà présentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('structures', function (Blueprint $table) {
            $table->foreign('responsable_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('programmes', function (Blueprint $table) {
            $table->foreign('pilier_id')->references('id')->on('piliers')->nullOnDelete();
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->foreign('ligne_budgetaire_id')->references('id')->on('lignes_budgetaires')->nullOnDelete();
        });

        Schema::table('lignes_budgetaires', function (Blueprint $table) {
            $table->foreign('activite_id')->references('id')->on('activites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('structures', fn (Blueprint $t) => $t->dropForeign(['responsable_user_id']));
        Schema::table('programmes', fn (Blueprint $t) => $t->dropForeign(['pilier_id']));
        Schema::table('activites', fn (Blueprint $t) => $t->dropForeign(['ligne_budgetaire_id']));
        Schema::table('lignes_budgetaires', fn (Blueprint $t) => $t->dropForeign(['activite_id']));
    }
};
