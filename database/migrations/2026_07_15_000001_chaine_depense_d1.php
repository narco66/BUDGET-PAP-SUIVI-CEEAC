<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase D1 — réservations, certificats, consolidation besoins. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignId('ligne_budgetaire_id')->constrained('lignes_budgetaires')->restrictOnDelete();
            $table->decimal('montant', 18, 2);
            $table->enum('statut', ['active', 'liberee'])->default('active');
            $table->timestamp('liberee_le')->nullable();
            $table->timestamps();
            $table->index(['engagement_id', 'statut']);
        });

        Schema::create('certificats_engagement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->string('numero', 40)->unique();
            $table->enum('type', ['reservation', 'engagement'])->default('engagement');
            $table->foreignId('emis_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emis_le');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('besoin_consolidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('reference', 60);
            $table->unsignedInteger('besoins_count')->default(0);
            $table->unsignedInteger('doublons_elimines')->default(0);
            $table->decimal('montant_total', 18, 2)->default(0);
            $table->json('snapshot');
            $table->enum('statut', ['brouillon', 'valide'])->default('valide');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['exercice_id', 'structure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('besoin_consolidations');
        Schema::dropIfExists('certificats_engagement');
        Schema::dropIfExists('reservations_credits');
    }
};
