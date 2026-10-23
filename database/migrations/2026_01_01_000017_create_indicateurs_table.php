<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indicateur rattaché polymorphiquement à un élément de la chaîne RBM. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicateurs', function (Blueprint $table) {
            $table->id();
            $table->morphs('rbm'); // rbm_id + rbm_type (Pilier, Axe, Produit, Activite...)
            $table->string('code', 40)->unique();
            $table->string('libelle');
            $table->enum('type', ['strategique', 'operationnel', 'financier', 'impact'])->default('operationnel');
            $table->string('unite', 50)->nullable();
            $table->text('methode_calcul')->nullable();
            $table->string('source_verification')->nullable();
            $table->enum('periodicite', ['mensuel', 'trimestriel', 'semestriel', 'annuel', 'pluriannuel'])->default('trimestriel');
            $table->decimal('valeur_reference', 18, 4)->nullable();
            $table->decimal('valeur_cible', 18, 4)->nullable();
            $table->decimal('seuil_alerte', 18, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicateurs');
    }
};
