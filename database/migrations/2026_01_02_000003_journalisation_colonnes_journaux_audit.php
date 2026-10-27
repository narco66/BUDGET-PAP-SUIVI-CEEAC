<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Journalisation, Traçabilité et Horodatage » : enrichit le journal central.
 * Placée juste après la création de journaux_audit, car AuditLogger écrit toujours ces colonnes
 * et des migrations ultérieures créent déjà des modèles audités (ex. structures).
 * ADDITIVE : colonnes nullables ajoutées seulement si absentes ; aucune ligne existante réécrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journaux_audit') || Schema::hasColumn('journaux_audit', 'uuid')) {
            return;
        }

        Schema::table('journaux_audit', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
            $table->string('code_evenement', 150)->nullable();
            $table->string('categorie', 20)->nullable();            // metier | securite | technique
            $table->string('gravite', 20)->nullable();              // information | faible | modere | eleve | critique
            $table->string('module', 30)->nullable();
            $table->string('resultat', 20)->nullable();             // succes | refus | echec
            $table->text('motif')->nullable();
            $table->string('reference_objet', 120)->nullable();
            $table->unsignedBigInteger('structure_id')->nullable();   // unité de l'acteur au moment de l'action
            $table->unsignedBigInteger('pour_compte_de')->nullable(); // titulaire représenté (délégation, intérim)
            $table->unsignedBigInteger('workflow_instance_id')->nullable();
            $table->unsignedBigInteger('workflow_tache_id')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('source', 20)->nullable();               // web | api | console
            $table->timestampTz('occurred_at', 6)->nullable();
            $table->timestampTz('recorded_at', 6)->nullable()->useCurrent();
            $table->timestampTz('effective_at', 6)->nullable();
            $table->char('empreinte', 64)->nullable();

            $table->index(['categorie', 'created_at']);
            $table->index(['auditable_type', 'auditable_id', 'id']);
            $table->index(['user_id', 'created_at']);
            $table->index('correlation_id');
            $table->index('workflow_instance_id');
            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('journaux_audit', 'uuid')) {
            return;
        }

        Schema::table('journaux_audit', function (Blueprint $table) {
            $table->dropIndex(['categorie', 'created_at']);
            $table->dropIndex(['auditable_type', 'auditable_id', 'id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['correlation_id']);
            $table->dropIndex(['workflow_instance_id']);
            $table->dropIndex(['module', 'created_at']);
            $table->dropUnique(['uuid']);
            $table->dropColumn([
                'uuid', 'code_evenement', 'categorie', 'gravite', 'module', 'resultat', 'motif', 'reference_objet',
                'structure_id', 'pour_compte_de', 'workflow_instance_id', 'workflow_tache_id', 'correlation_id',
                'request_id', 'source', 'occurred_at', 'recorded_at', 'effective_at', 'empreinte',
            ]);
        });
    }
};
