<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase N-P1 — registre alertes, règles de relance, journal unifié. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->string('module', 50);
            $table->string('code', 80);
            $table->string('criticite', 20)->default('attention');
            $table->string('titre');
            $table->text('message');
            $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
            $table->nullableMorphs('objet');
            $table->string('statut', 20)->default('ouverte');
            $table->foreignId('assigne_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolu_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolu_le')->nullable();
            $table->text('resolu_commentaire')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('detecte_le');
            $table->timestamps();

            $table->index(['module', 'statut', 'criticite']);
            $table->index(['exercice_id', 'statut']);
        });

        Schema::create('relance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('module', 50);
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->unsignedInteger('intervalle_jours')->default(7);
            $table->string('notification_type_code', 80)->nullable();
            $table->json('roles_cibles')->nullable();
            $table->json('parametres')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('relance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relance_rule_id')->constrained('relance_rules')->cascadeOnDelete();
            $table->string('module', 50);
            $table->nullableMorphs('objet');
            $table->foreignId('destinataire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('canal', 20)->default('database');
            $table->string('statut', 20)->default('envoye');
            $table->string('titre');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('emis_le');
            $table->timestamps();

            $table->index(['relance_rule_id', 'emis_le']);
            $table->index(['module', 'objet_type', 'objet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relance_logs');
        Schema::dropIfExists('relance_rules');
        Schema::dropIfExists('alertes');
    }
};
