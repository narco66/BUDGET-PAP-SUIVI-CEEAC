<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Contrats (liés aux marchés et/ou engagements). Création non destructive. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contrats')) {
            return;
        }

        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('marche_id')->nullable()->constrained('marches')->nullOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('objet');
            $table->string('titulaire');
            $table->enum('type_contrat', ['marche', 'convention', 'bon_commande', 'prestation'])->default('marche');
            $table->decimal('montant', 18, 2)->default(0);
            $table->date('date_signature')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->enum('statut', ['projet', 'signe', 'en_cours', 'execute', 'resilie', 'suspendu'])->default('projet');
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exercice_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};
