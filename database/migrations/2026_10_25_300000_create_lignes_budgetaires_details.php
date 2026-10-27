<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Détail du budget de fonctionnement (codes à 5 chiffres du document budgétaire, ex. 66321) rattaché aux lignes
 * budgétaires existantes, qui restent la maille d'imputation et d'engagement. ADDITIVE : aucune ligne ni aucun
 * montant du budget n'est modifié.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_budgetaires_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->foreignId('ligne_budgetaire_id')->constrained('lignes_budgetaires');
            $table->string('code', 10);
            $table->string('intitule');
            $table->decimal('montant_prevu', 18, 2)->default(0);
            $table->string('source', 120)->nullable();
            $table->timestamps();

            $table->unique(['exercice_id', 'code']);
            $table->index('ligne_budgetaire_id');
        });

        Schema::table('expressions_besoin', function (Blueprint $table) {
            $table->foreignId('ligne_detail_id')->nullable()->after('ligne_budgetaire_id')
                ->constrained('lignes_budgetaires_details')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expressions_besoin', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ligne_detail_id');
        });
        Schema::dropIfExists('lignes_budgetaires_details');
    }
};
