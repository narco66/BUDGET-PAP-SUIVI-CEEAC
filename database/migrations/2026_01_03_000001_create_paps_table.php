<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Annuel de Performance (D3) : un PAP par exercice.
 * Objet porteur du cycle de vie soumis au moteur de workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->unique()->constrained('exercices')->cascadeOnDelete();
            $table->string('libelle');
            $table->enum('statut', [
                'brouillon', 'en_validation', 'valide', 'publie', 'rejete', 'clos', 'en_revision',
            ])->default('brouillon');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paps');
    }
};
