<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Conventions et accords de financement PTF (Phase B). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conventions_financement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partenaire_id')->constrained('partenaires_financiers')->cascadeOnDelete();
            $table->foreignId('source_financement_id')->nullable()->constrained('sources_financement')->nullOnDelete();
            $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('libelle');
            $table->string('reference_accord', 100)->nullable();
            $table->date('date_signature')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->decimal('montant_accord', 18, 2)->default(0);
            $table->decimal('montant_engage', 18, 2)->default(0);
            $table->decimal('montant_execute', 18, 2)->default(0);
            $table->decimal('montant_decaisse', 18, 2)->default(0);
            $table->enum('statut', ['brouillon', 'actif', 'suspendu', 'clos'])->default('brouillon');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conventions_financement');
    }
};
