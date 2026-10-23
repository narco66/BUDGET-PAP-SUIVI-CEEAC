<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ateliers et formations (participation, coûts, répartition genre, impact). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('formations')) {
            return;
        }

        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->nullOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
            $table->string('intitule');
            $table->enum('type', ['formation', 'atelier', 'seminaire', 'conference'])->default('formation');
            $table->string('thematique')->nullable();
            $table->string('lieu')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->unsignedInteger('nb_participants')->default(0);
            $table->unsignedInteger('nb_hommes')->default(0);
            $table->unsignedInteger('nb_femmes')->default(0);
            $table->decimal('cout', 18, 2)->default(0);
            $table->decimal('niveau_satisfaction', 5, 2)->nullable();
            $table->text('impact')->nullable();
            $table->enum('statut', ['planifie', 'realise', 'annule'])->default('planifie');
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exercice_id', 'type', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
