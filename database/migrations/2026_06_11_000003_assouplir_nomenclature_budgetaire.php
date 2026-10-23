<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La nomenclature CEEAC réelle :
 *  - comporte des « sens » au-delà de recette/dépense (financement, hors-bilan) → sens en chaîne ;
 *  - réutilise les mêmes codes de rubrique sous des paragraphes différents (ex. 741100 en Titre 2 et 3)
 *    → unicité de la rubrique portée sur (paragraphe_id, code) et non plus sur le code seul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_titres', function (Blueprint $table) {
            $table->string('sens', 20)->change();
        });

        Schema::table('budget_rubriques', function (Blueprint $table) {
            $table->dropUnique('budget_rubriques_code_unique');
            $table->unique(['paragraphe_id', 'code']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::table('budget_rubriques', function (Blueprint $table) {
            $table->dropUnique(['paragraphe_id', 'code']);
            $table->dropIndex(['code']);
            $table->unique('code');
        });

        Schema::table('budget_titres', function (Blueprint $table) {
            $table->enum('sens', ['recette', 'depense'])->change();
        });
    }
};
