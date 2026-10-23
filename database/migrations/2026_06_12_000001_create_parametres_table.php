<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paramètres système (module Paramètres) — clé/valeur typée. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 80)->unique();
            $table->text('valeur')->nullable();
            $table->string('libelle');
            $table->string('groupe', 50)->default('general');
            $table->enum('type', ['string', 'number', 'bool', 'json'])->default('string');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};
