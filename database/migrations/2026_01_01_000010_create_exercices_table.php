<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Exercice budgétaire (rattachement PAP + budget). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercices', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee')->unique();
            $table->string('libelle');
            $table->enum('statut', ['brouillon', 'ouvert', 'clos'])->default('brouillon');
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->string('devise', 3)->default('XAF');
            $table->decimal('taux_change_usd', 18, 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercices');
    }
};
