<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Données collectées d'exécution physique/financière par activité (D7 — EF-D7-03..06). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collectes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campagne_id')->constrained('campagnes_suivi')->cascadeOnDelete();
            $table->foreignId('activite_id')->constrained('activites')->cascadeOnDelete();
            $table->decimal('taux_physique', 5, 2)->default(0);   // saisi (0-100)
            $table->decimal('montant_realise', 18, 2)->default(0); // exécution financière
            $table->decimal('taux_financier', 5, 2)->default(0);   // calculé
            $table->decimal('ecart', 6, 2)->default(0);            // physique - financier
            $table->text('commentaire')->nullable();
            $table->enum('statut', ['saisie', 'verifiee', 'validee'])->default('saisie');
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['campagne_id', 'activite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collectes');
    }
};
