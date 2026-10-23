<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Étapes ordonnées d'un circuit, chacune affectée à un rôle. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedInteger('ordre');
            $table->string('libelle');
            $table->string('role', 50); // nom du rôle Spatie habilité à agir
            $table->unsignedInteger('delai_jours')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_etapes');
    }
};
