<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P0 Suivi-Évaluation — snapshots de scores agrégés par exercice / campagne. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evaluations_periodiques')) {
            return;
        }

        Schema::create('evaluations_periodiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('campagne_id')->nullable()->constrained('campagnes_suivi')->nullOnDelete();
            $table->string('periode', 40);
            $table->enum('type', ['periodique', 'campagne', 'annuelle'])->default('periodique');
            $table->decimal('score_physique', 5, 2)->default(0);
            $table->decimal('score_iov', 5, 2)->default(0);
            $table->decimal('score_recommandations', 5, 2)->default(0);
            $table->decimal('score_formations', 5, 2)->default(0);
            $table->decimal('score_conformite', 5, 2)->default(0);
            $table->decimal('score_global', 5, 2)->default(0);
            $table->enum('niveau', ['satisfaisant', 'partiel', 'insuffisant'])->default('insuffisant');
            $table->json('details')->nullable();
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('genere_le')->useCurrent();
            $table->timestamps();

            $table->unique(['exercice_id', 'periode', 'type'], 'eval_per_ex_per_type_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations_periodiques');
    }
};
