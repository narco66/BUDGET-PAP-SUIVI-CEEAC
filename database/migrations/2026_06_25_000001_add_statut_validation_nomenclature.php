<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow de validation de la nomenclature (L5) : ajout d'un statut « brouillon | valide » sur les
 * quatre niveaux Titre / Article / Paragraphe / Rubrique.
 * NON DESTRUCTIF : colonne ajoutée seulement si absente (hasColumn), avec défaut « valide » afin que
 * toute la nomenclature existante (et les imports en masse) reste immédiatement exploitable.
 */
return new class extends Migration
{
    private array $niveaux = ['budget_titres', 'budget_articles', 'budget_paragraphes', 'budget_rubriques'];

    public function up(): void
    {
        foreach ($this->niveaux as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'statut')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('statut', 20)->default('valide')->after('ordre'));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->niveaux as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'statut')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('statut'));
            }
        }
    }
};
