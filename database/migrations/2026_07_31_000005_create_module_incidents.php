<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Administration Technique — Lot T2 (gestion des incidents, ITIL).
 * Cycle : Déclaration → Diagnostic → Correction → Validation → Clôture.
 * NON DESTRUCTIF (créations conditionnelles).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incident_categories')) {
            Schema::create('incident_categories', function (Blueprint $t) {
                $t->id();
                $t->string('code', 30)->unique();
                $t->string('libelle');
                $t->text('description')->nullable();
                $t->unsignedInteger('ordre')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('incidents')) {
            Schema::create('incidents', function (Blueprint $t) {
                $t->id();
                $t->string('numero', 40)->unique();
                $t->foreignId('incident_categorie_id')->nullable()->constrained('incident_categories')->nullOnDelete();
                $t->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
                $t->foreignId('declare_par')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('assigne_a')->nullable()->constrained('users')->nullOnDelete();
                $t->string('titre');
                $t->text('description')->nullable();
                $t->string('gravite', 20)->default('moyenne');     // faible|moyenne|elevee|critique
                $t->string('source', 20)->default('manuelle');     // manuelle|monitoring|alerte
                $t->string('statut', 20)->default('ouvert');       // ouvert|en_diagnostic|en_correction|en_validation|resolu|clos|rejete
                $t->text('impact')->nullable();
                $t->text('cause_racine')->nullable();
                $t->text('resolution')->nullable();
                $t->timestamp('date_detection')->nullable();
                $t->timestamp('date_resolution')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['statut', 'gravite']);
            });
        }

        if (! Schema::hasTable('incident_actions')) {
            Schema::create('incident_actions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
                $t->foreignId('auteur_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('type', 20)->default('diagnostic');     // diagnostic|correction|validation|communication|cloture|rejet
                $t->string('libelle');
                $t->text('description')->nullable();
                $t->string('statut_avant', 20)->nullable();
                $t->string('statut_apres', 20)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['incident_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_actions');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('incident_categories');
    }
};
