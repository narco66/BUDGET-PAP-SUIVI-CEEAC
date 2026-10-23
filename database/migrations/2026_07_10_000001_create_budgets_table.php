<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase B1 — enveloppe budgétaire institutionnelle (1 par exercice). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->unique()->constrained('exercices')->cascadeOnDelete();
            $table->string('reference', 40)->nullable()->unique();
            $table->string('libelle');
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_elaboration')->nullable();
            $table->text('observations')->nullable();
            $table->text('motif_rejet')->nullable();
            $table->enum('statut', [
                'brouillon', 'en_validation', 'valide', 'adopte', 'rejete', 'en_revision', 'clos',
            ])->default('brouillon');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adopte_le')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('montant_total', 18, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('structure_id');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
