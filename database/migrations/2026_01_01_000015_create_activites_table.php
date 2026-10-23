<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activité = niveau opérationnel exécutable, rattaché à une structure et
 * (optionnellement) à une ligne budgétaire. Porte le chronogramme trimestriel
 * et la ventilation CEEAC-EM / PTF conforme aux états du budget 2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sous_produit_id')->nullable()->constrained('sous_produits')->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained('produits')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            // ligne_budgetaire_id : FK ajoutée dans la cross migration
            $table->unsignedBigInteger('ligne_budgetaire_id')->nullable();

            $table->string('code', 40);
            $table->string('intitule', 1000);
            $table->text('description')->nullable();

            // Ventilation par source (états budget 2026)
            $table->decimal('montant_total', 18, 2)->default(0);
            $table->decimal('montant_ceeac', 18, 2)->default(0);
            $table->decimal('montant_ptf', 18, 2)->default(0);

            // Chronogramme trimestriel
            $table->boolean('trim_1')->default(false);
            $table->boolean('trim_2')->default(false);
            $table->boolean('trim_3')->default(false);
            $table->boolean('trim_4')->default(false);

            $table->decimal('taux_realisation_physique', 5, 2)->default(0);
            $table->decimal('taux_realisation_financier', 5, 2)->default(0);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('structure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activites');
    }
};
