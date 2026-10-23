<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paiement (D5 — EF-D5-06 : exécution + suivi). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('ordonnancement_id')->constrained('ordonnancements')->restrictOnDelete();
            $table->decimal('montant', 18, 2)->default(0);
            $table->date('date_paiement')->nullable();
            $table->enum('mode_paiement', ['virement', 'cheque', 'especes'])->default('virement');
            $table->string('reference_bancaire')->nullable();
            $table->enum('statut', ['brouillon', 'soumis', 'valide', 'rejete'])->default('brouillon');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->boolean('compteurs_appliques')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
