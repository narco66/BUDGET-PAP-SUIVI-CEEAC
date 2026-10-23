<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Expression du besoin (D5 — amont de la dépense). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expressions_besoin', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();
            $table->foreignId('demandeur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('objet');
            $table->text('justification')->nullable();
            $table->decimal('montant_estime', 18, 2)->default(0);
            $table->date('date_besoin')->nullable();
            $table->enum('statut', ['brouillon', 'soumis', 'valide', 'rejete', 'converti'])->default('brouillon');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expressions_besoin');
    }
};
