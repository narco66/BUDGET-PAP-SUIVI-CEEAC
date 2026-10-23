<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rubrique = niveau le plus fin de la nomenclature (ex: 661000, 601100...). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_rubriques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paragraphe_id')->constrained('budget_paragraphes')->cascadeOnDelete();
            $table->string('code', 15)->unique();
            $table->string('intitule');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_rubriques');
    }
};
