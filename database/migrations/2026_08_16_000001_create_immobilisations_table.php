<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P2-1 — registre des immobilisations et inventaire patrimonial. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immobilisations', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
            $table->string('categorie', 30);
            $table->string('designation');
            $table->string('marque', 120)->nullable();
            $table->string('numero_serie', 120)->nullable();
            $table->string('localisation', 255)->nullable();
            $table->string('affectation', 255)->nullable();
            $table->date('date_acquisition');
            $table->decimal('valeur_acquisition', 18, 2)->default(0);
            $table->unsignedSmallInteger('duree_amortissement_ans')->default(5);
            $table->string('compte_syscohada', 20)->nullable();
            $table->string('statut', 20)->default('en_service');
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exercice_id', 'categorie']);
            $table->index(['exercice_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immobilisations');
    }
};
