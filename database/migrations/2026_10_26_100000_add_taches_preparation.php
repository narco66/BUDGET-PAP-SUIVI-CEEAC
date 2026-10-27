<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « Mes Tâches » couvre aussi les actions humaines hors étape de circuit (soumettre, corriger, préparer
 * un paiement, solder un reliquat) : la tâche peut alors exister sans instance de workflow.
 * Additive : aucune donnée existante n'est modifiée (les tâches actuelles restent de nature « validation »).
 */
return new class extends Migration
{
    private const OUVERTS = "statut IN ('a_traiter', 'en_cours', 'suspendue')";

    public function up(): void
    {
        Schema::table('workflow_taches', function (Blueprint $table) {
            $table->foreignId('workflow_instance_id')->nullable()->change();
            $table->foreignId('workflow_instance_etape_id')->nullable()->change();
        });

        Schema::table('workflow_taches', function (Blueprint $table) {
            $table->string('nature', 20)->default('validation')->after('reference');
            $table->string('action_code', 40)->nullable()->after('nature');
            // Aucun titulaire organique trouvé : la tâche est ouverte à tous les porteurs du rôle (signalée).
            $table->boolean('affectation_defaut')->default(false)->after('role');
            $table->index(['statut', 'created_at']);
        });

        // La reconstruction SQLite d'un change() ne conserve pas la clause WHERE des index partiels.
        DB::statement('DROP INDEX IF EXISTS workflow_taches_une_ouverte_par_etape');
        DB::statement('CREATE UNIQUE INDEX workflow_taches_une_ouverte_par_etape ON workflow_taches (workflow_instance_etape_id) WHERE '.self::OUVERTS);
        // Une seule tâche de préparation ouverte par dossier et par action (idempotence de la projection).
        DB::statement('CREATE UNIQUE INDEX workflow_taches_une_preparation_ouverte ON workflow_taches (objet_type, objet_id, action_code) WHERE '.self::OUVERTS." AND nature = 'preparation'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS workflow_taches_une_preparation_ouverte');
        Schema::table('workflow_taches', function (Blueprint $table) {
            $table->dropIndex(['statut', 'created_at']);
            $table->dropColumn(['nature', 'action_code', 'affectation_defaut']);
        });
        // Les tâches de préparation n'ont pas d'instance : elles sont retirées avant de rétablir NOT NULL.
        DB::table('workflow_taches')->whereNull('workflow_instance_id')->delete();
        Schema::table('workflow_taches', function (Blueprint $table) {
            $table->foreignId('workflow_instance_id')->nullable(false)->change();
            $table->foreignId('workflow_instance_etape_id')->nullable(false)->change();
        });
        DB::statement('DROP INDEX IF EXISTS workflow_taches_une_ouverte_par_etape');
        DB::statement('CREATE UNIQUE INDEX workflow_taches_une_ouverte_par_etape ON workflow_taches (workflow_instance_etape_id) WHERE '.self::OUVERTS);
    }
};
