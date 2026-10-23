<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nomenclature par nature (D4) — niveau Titre. sens = recette | depense. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_titres', function (Blueprint $table) {
            $table->id();
            $table->enum('sens', ['recette', 'depense']);
            $table->string('code', 10);
            $table->string('intitule');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['sens', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_titres');
    }
};
