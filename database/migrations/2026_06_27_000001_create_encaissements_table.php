<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase R3 — encaissements des recettes : saisie, validation, rapprochement bancaire.
 * Migration additive : ne modifie aucune table existante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encaissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recette_id')->constrained('recettes')->cascadeOnDelete();
            $table->string('reference', 60)->unique();
            $table->decimal('montant', 18, 2)->default(0);
            $table->string('devise', 3)->default('XAF');
            $table->decimal('taux_change', 18, 6)->default(1);
            $table->decimal('montant_xaf', 18, 2)->default(0);
            $table->string('mode_paiement', 30)->default('virement');
            $table->string('banque', 120)->nullable();
            $table->string('reference_bancaire', 100)->nullable();
            $table->date('date_encaissement');
            $table->string('statut', 20)->default('saisi');
            $table->boolean('rapproche')->default(false);
            $table->date('date_rapprochement')->nullable();
            $table->foreignId('rapproche_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['recette_id', 'statut']);
            $table->index('date_encaissement');
            $table->index(['statut', 'rapproche']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encaissements');
    }
};
