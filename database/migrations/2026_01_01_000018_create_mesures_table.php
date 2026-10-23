<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicateur_id')->constrained('indicateurs')->cascadeOnDelete();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('periode', 20); // ex: 2026-T1
            $table->decimal('valeur_realisee', 18, 4);
            $table->decimal('taux_atteinte', 7, 2)->nullable();
            $table->enum('feu', ['vert', 'orange', 'rouge'])->nullable();
            $table->text('commentaire')->nullable();
            $table->enum('statut', ['saisie', 'verifiee', 'validee'])->default('saisie');
            $table->timestamps();

            $table->unique(['indicateur_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesures');
    }
};
