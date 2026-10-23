<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Administration Technique — Lot T3 (sauvegardes & restauration, ISO 22301).
 * `backup_schedules` (planification) · `backups` (registre des exécutions) · `restore_operations`
 * (workflow de restauration contrôlé : demande → validation 4-yeux → exécution tracée).
 * NON DESTRUCTIF (créations conditionnelles).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backup_schedules')) {
            Schema::create('backup_schedules', function (Blueprint $t) {
                $t->id();
                $t->string('libelle');
                $t->string('type', 20)->default('base');        // base|fichiers|complet
                $t->string('frequence', 20)->default('quotidienne'); // quotidienne|hebdomadaire|mensuelle|annuelle
                $t->string('heure', 5)->default('02:00');
                $t->unsignedInteger('retention_jours')->default(30);
                $t->boolean('actif')->default(true);
                $t->timestamp('derniere_execution_le')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('backups')) {
            Schema::create('backups', function (Blueprint $t) {
                $t->id();
                $t->string('reference', 50)->unique();
                $t->string('type', 20)->default('base');
                $t->string('declenchement', 12)->default('manuel'); // manuel|auto
                $t->string('chemin')->nullable();
                $t->unsignedBigInteger('taille_octets')->nullable();
                $t->string('checksum', 64)->nullable();             // SHA-256
                $t->string('statut', 12)->default('en_cours');      // en_cours|succes|echec
                $t->unsignedInteger('duree_ms')->nullable();
                $t->text('erreur')->nullable();
                $t->timestamp('demarre_le')->nullable();
                $t->timestamp('termine_le')->nullable();
                $t->foreignId('declenche_par')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->index(['statut', 'created_at']);
            });
        }

        if (! Schema::hasTable('restore_operations')) {
            Schema::create('restore_operations', function (Blueprint $t) {
                $t->id();
                $t->string('reference', 50)->unique();
                $t->foreignId('backup_id')->nullable()->constrained('backups')->nullOnDelete();
                $t->string('type', 20)->default('complet');         // complet|partiel|base|documentaire
                $t->string('statut', 12)->default('demande');       // demande|validee|rejetee|executee
                $t->string('point_restauration')->nullable();
                $t->text('motif')->nullable();
                $t->text('motif_rejet')->nullable();
                $t->foreignId('demande_par')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('execute_par')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('valide_le')->nullable();
                $t->timestamp('execute_le')->nullable();
                $t->timestamps();
                $t->index(['statut', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_operations');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('backup_schedules');
    }
};
