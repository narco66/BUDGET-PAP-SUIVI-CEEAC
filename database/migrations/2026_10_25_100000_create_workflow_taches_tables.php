<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Mes Tâches » : une tâche par passage d'une étape de workflow à l'état « à traiter »,
 * et son journal immuable. Tables additives, rattachées au moteur existant (instances / étapes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_taches', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances');
            $table->foreignId('workflow_instance_etape_id')->constrained('workflow_instance_etapes');
            $table->string('workflow_code', 60);
            $table->string('module', 20);
            $table->string('objet_type');
            $table->unsignedBigInteger('objet_id');
            $table->string('objet_libelle')->nullable();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->unsignedSmallInteger('etape_ordre');
            $table->string('role', 60);
            $table->string('libelle_etape')->nullable();
            $table->enum('statut', ['a_traiter', 'en_cours', 'suspendue', 'terminee', 'annulee'])->default('a_traiter');
            $table->enum('priorite', ['faible', 'normale', 'haute', 'urgente'])->default('normale');
            $table->boolean('priorite_manuelle')->default(false);
            $table->string('decision', 20)->nullable();
            $table->foreignId('assigne_a')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pris_en_charge_le')->nullable();
            $table->timestamp('suspendue_le')->nullable();
            $table->text('motif_suspension')->nullable();
            $table->timestamp('echeance_le')->nullable();
            $table->timestamp('termine_le')->nullable();
            $table->foreignId('termine_par')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['statut', 'role']);
            $table->index('assigne_a');
            $table->index('echeance_le');
            $table->index('module');
            $table->index(['objet_type', 'objet_id']);
            $table->index('termine_par');
        });

        // Une seule tâche ouverte par étape d'instance : protège la création contre les doublons concurrents.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX workflow_taches_une_ouverte_par_etape ON workflow_taches (workflow_instance_etape_id) WHERE statut IN ('a_traiter', 'en_cours', 'suspendue')");
        }

        Schema::create('workflow_tache_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_tache_id')->constrained('workflow_taches')->cascadeOnDelete();
            $table->string('type', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pour_compte_de')->nullable()->constrained('users')->nullOnDelete();
            $table->text('commentaire')->nullable();
            $table->json('donnees')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workflow_tache_id', 'created_at']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_tache_evenements');
        Schema::dropIfExists('workflow_taches');
    }
};
