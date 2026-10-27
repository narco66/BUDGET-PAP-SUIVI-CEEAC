<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Notifications (référentiel du 25/10/2026) : nature, état d'action indépendant de la lecture,
 * dossier lié et clé de déduplication, sur la table Laravel existante (une ligne par destinataire).
 * Sous PostgreSQL, « data » passe en jsonb : la recherche et les filtres sur son contenu échouaient sur du text.
 * Réversible ; aucune notification n'est supprimée ni réécrite par la migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('nature', 20)->default('information')->after('criticite');
            $table->string('action_statut', 20)->nullable()->after('nature');
            $table->timestamp('action_traitee_le')->nullable()->after('action_statut');
            $table->string('ressource_type')->nullable()->after('action_traitee_le');
            $table->unsignedBigInteger('ressource_id')->nullable()->after('ressource_type');
            $table->string('reference', 60)->nullable()->after('ressource_id');
            $table->string('cle_deduplication', 150)->nullable()->after('reference');

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_destinataire_lecture_index');
            $table->index(['notifiable_id', 'action_statut'], 'notifications_destinataire_action_index');
            $table->index(['ressource_type', 'ressource_id'], 'notifications_ressource_index');
            $table->index(['notifiable_id', 'cle_deduplication'], 'notifications_deduplication_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_nature_check CHECK (nature IN ('action','information','alerte','rappel','systeme'))");
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_action_statut_check CHECK (action_statut IS NULL OR action_statut IN ('en_attente','traitee','annulee','expiree'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_nature_check');
            DB::statement('ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_action_statut_check');
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_destinataire_lecture_index');
            $table->dropIndex('notifications_destinataire_action_index');
            $table->dropIndex('notifications_ressource_index');
            $table->dropIndex('notifications_deduplication_index');
            $table->dropColumn(['nature', 'action_statut', 'action_traitee_le', 'ressource_type', 'ressource_id', 'reference', 'cle_deduplication']);
        });
    }
};
