<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ligne budgétaire — cœur du contrôle des crédits.
 * Les colonnes montant_* matérialisent l'état de consommation en temps réel :
 * disponible = montant_ouvert - montant_engage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_budgetaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('rubrique_id')->constrained('budget_rubriques')->restrictOnDelete();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->nullOnDelete();
            // activite_id : FK ajoutée dans la cross migration (rattachement RBM)
            $table->unsignedBigInteger('activite_id')->nullable();

            $table->string('code', 50);
            $table->string('intitule')->nullable();

            // Compteurs de la chaîne de la dépense
            $table->decimal('montant_ouvert', 18, 2)->default(0);
            $table->decimal('montant_engage', 18, 2)->default(0);
            $table->decimal('montant_liquide', 18, 2)->default(0);
            $table->decimal('montant_ordonnance', 18, 2)->default(0);
            $table->decimal('montant_paye', 18, 2)->default(0);

            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exercice_id', 'code']);
            $table->index(['exercice_id', 'structure_id']);
            $table->index('rubrique_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_budgetaires');
    }
};
