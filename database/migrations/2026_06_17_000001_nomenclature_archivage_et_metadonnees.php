<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extension NON DESTRUCTIVE de la nomenclature budgétaire :
 *  - archivage logique (soft delete) sur Titre / Article / Paragraphe / Rubrique ;
 *  - métadonnées de ligne budgétaire (nature, date d'entrée en vigueur).
 * Toutes les colonnes sont ajoutées si absentes (hasColumn) : aucune donnée existante n'est touchée.
 */
return new class extends Migration
{
    private array $niveaux = ['budget_titres', 'budget_articles', 'budget_paragraphes', 'budget_rubriques'];

    public function up(): void
    {
        foreach ($this->niveaux as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
            }
        }

        if (Schema::hasTable('lignes_budgetaires')) {
            Schema::table('lignes_budgetaires', function (Blueprint $t) {
                if (! Schema::hasColumn('lignes_budgetaires', 'nature')) {
                    $t->string('nature', 50)->nullable()->after('intitule');
                }
                if (! Schema::hasColumn('lignes_budgetaires', 'date_entree_vigueur')) {
                    $t->date('date_entree_vigueur')->nullable()->after('nature');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->niveaux as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
            }
        }

        if (Schema::hasTable('lignes_budgetaires')) {
            Schema::table('lignes_budgetaires', function (Blueprint $t) {
                foreach (['nature', 'date_entree_vigueur'] as $col) {
                    if (Schema::hasColumn('lignes_budgetaires', $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
