<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Workflow (référentiel du 25/10/2026) — conservation de l'historique et intégrité :
 *  - une étape de circuit utilisée par des dossiers ne peut plus être supprimée (la cascade effaçait leurs décisions) ;
 *  - le libellé de l'étape est conservé sur l'instance (l'historique ne dépend plus de la définition vivante) ;
 *  - une seule instance « en cours » par dossier (double soumission concurrente).
 * Réversible ; aucune décision existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instance_etapes', function (Blueprint $table) {
            $table->dropForeign(['workflow_etape_id']);
        });
        Schema::table('workflow_instance_etapes', function (Blueprint $table) {
            $table->foreign('workflow_etape_id')->references('id')->on('workflow_etapes')->restrictOnDelete();
            $table->string('libelle')->nullable()->after('role');
        });

        // Libellé à la date de la reprise : seul disponible pour les étapes déjà créées.
        DB::table('workflow_instance_etapes')->whereNull('libelle')->orderBy('id')->each(function ($e) {
            $libelle = DB::table('workflow_etapes')->where('id', $e->workflow_etape_id)->value('libelle');
            if ($libelle !== null) {
                DB::table('workflow_instance_etapes')->where('id', $e->id)->update(['libelle' => $libelle]);
            }
        });

        DB::statement("CREATE UNIQUE INDEX workflow_instances_une_en_cours_par_objet ON workflow_instances (objet_type, objet_id) WHERE statut = 'en_cours'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS workflow_instances_une_en_cours_par_objet');
        Schema::table('workflow_instance_etapes', function (Blueprint $table) {
            $table->dropForeign(['workflow_etape_id']);
            $table->dropColumn('libelle');
        });
        Schema::table('workflow_instance_etapes', function (Blueprint $table) {
            $table->foreign('workflow_etape_id')->references('id')->on('workflow_etapes')->cascadeOnDelete();
        });
    }
};
