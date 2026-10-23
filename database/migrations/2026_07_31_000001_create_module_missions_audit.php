<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Audit, Contrôle Interne & Conformité — Lot B (missions d'audit, INTOSAI).
 * Cycle : Mission → Constat → Recommandation → Suivi. NON DESTRUCTIF (créations conditionnelles).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions_audit')) {
            Schema::create('missions_audit', function (Blueprint $t) {
                $t->id();
                $t->string('numero', 40)->unique();
                $t->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
                $t->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete(); // entité auditée
                $t->foreignId('chef_mission_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('type', 30)->default('interne');   // interne|externe|conformite|performance|financier
                $t->string('libelle');
                $t->string('domaine', 150)->nullable();        // processus / domaine audité
                $t->text('objectif')->nullable();
                $t->text('perimetre')->nullable();
                $t->date('date_debut')->nullable();
                $t->date('date_fin')->nullable();
                $t->string('statut', 20)->default('planifiee'); // planifiee|en_cours|cloturee|annulee
                $t->text('conclusion')->nullable();
                $t->unsignedTinyInteger('score_conformite')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['exercice_id', 'statut']);
            });
        }

        if (! Schema::hasTable('constats_audit')) {
            Schema::create('constats_audit', function (Blueprint $t) {
                $t->id();
                $t->string('numero', 40)->unique();
                $t->foreignId('mission_audit_id')->constrained('missions_audit')->cascadeOnDelete();
                $t->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('libelle');
                $t->text('description')->nullable();
                $t->text('cause')->nullable();
                $t->string('gravite', 20)->default('moyenne');  // faible|moyenne|elevee|critique
                $t->text('recommandation')->nullable();
                $t->date('echeance')->nullable();
                $t->string('statut', 20)->default('ouvert');     // ouvert|en_cours|realise|non_realise
                $t->unsignedTinyInteger('taux_avancement')->default(0);
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['mission_audit_id', 'statut']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('constats_audit');
        Schema::dropIfExists('missions_audit');
    }
};
