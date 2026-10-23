<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase S3 — snapshot consolidation sous-produits à la clôture de campagne. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('realisation_sous_produits')) {
            Schema::create('realisation_sous_produits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campagne_id')->constrained('campagnes_suivi')->cascadeOnDelete();
                $table->foreignId('sous_produit_id')->constrained('sous_produits')->cascadeOnDelete();
                $table->decimal('taux_physique', 5, 2)->default(0);
                $table->decimal('taux_financier', 5, 2)->default(0);
                $table->unsignedInteger('nb_activites')->default(0);
                $table->unsignedInteger('nb_collectes_validees')->default(0);
                $table->timestamps();

                $table->unique(['campagne_id', 'sous_produit_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('realisation_sous_produits');
    }
};
