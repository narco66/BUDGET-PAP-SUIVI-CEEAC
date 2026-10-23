<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P2 Suivi Indicateurs — snapshots persistés des performances IOV. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performances_indicateurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
            $table->foreignId('indicateur_id')->constrained('indicateurs')->cascadeOnDelete();
            $table->string('periode', 20);
            $table->decimal('valeur_cible', 18, 4)->nullable();
            $table->decimal('valeur_realisee', 18, 4)->default(0);
            $table->decimal('taux_atteinte', 8, 2)->default(0);
            $table->string('feu', 10)->nullable();
            $table->foreignId('mesure_id')->nullable()->constrained('mesures')->nullOnDelete();
            $table->foreignId('capture_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('capture_le')->useCurrent();

            $table->unique(['indicateur_id', 'periode']);
            $table->index(['exercice_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performances_indicateurs');
    }
};
