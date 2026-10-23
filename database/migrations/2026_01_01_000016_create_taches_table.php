<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activite_id')->constrained('activites')->cascadeOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40);
            $table->string('intitule', 1000);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->decimal('montant', 18, 2)->default(0);
            $table->decimal('taux_realisation', 5, 2)->default(0);
            $table->enum('statut', ['a_faire', 'en_cours', 'termine', 'bloque'])->default('a_faire');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taches');
    }
};
