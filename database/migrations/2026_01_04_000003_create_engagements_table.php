<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Engagement budgétaire (D5 — EF-D5-01..03). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('expression_besoin_id')->nullable()->constrained('expressions_besoin')->nullOnDelete();
            $table->enum('type', [
                'simple', 'bon_commande', 'marche', 'mission', 'subvention',
                'convention', 'pluriannuel', 'provisionnel',
            ])->default('simple');
            $table->string('objet');
            $table->string('beneficiaire')->nullable();
            $table->decimal('montant_total', 18, 2)->default(0);
            $table->date('date_engagement')->nullable();
            $table->enum('statut', ['brouillon', 'soumis', 'vise', 'rejete', 'annule'])->default('brouillon');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('controleur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vise_le')->nullable();
            $table->boolean('credits_reserves')->default(false); // les compteurs des lignes sont-ils incrémentés ?
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagements');
    }
};
