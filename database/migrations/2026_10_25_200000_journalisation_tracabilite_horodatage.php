<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Journalisation, Traçabilité et Horodatage » — migration ADDITIVE.
 * - Les colonnes du journal central sont ajoutées par 2026_01_02_000003 (avant les modèles audités).
 * - Tables de scellement, de contrôle d'intégrité, d'export et d'archivage.
 * - PostgreSQL : déclencheurs append-only sur les journaux (UPDATE/DELETE refusés, hors
 *   mise à NULL d'une clé étrangère par ON DELETE SET NULL et hors maintenance autorisée).
 */
return new class extends Migration
{
    /** @var array<string, list<string>> journal protégé → clés étrangères pouvant passer à NULL */
    private const JOURNAUX_PROTEGES = [
        'journaux_audit' => ['user_id'],
        'login_logs' => ['user_id'],
        'audit_workflow' => ['acteur_id', 'workflow_instance_id'],
        'ged_journaux' => ['document_id', 'utilisateur_id'],
        'audit_scellements' => [],
        'audit_controles_integrite' => [],
        'audit_exports' => [],
        'audit_archives' => [],
    ];

    public function up(): void
    {
        Schema::create('audit_scellements', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('de_id');
            $table->unsignedBigInteger('a_id');
            $table->unsignedInteger('nombre');
            $table->char('empreinte_precedente', 64);
            $table->char('empreinte_tete', 64);
            $table->char('signature', 64);                         // HMAC-SHA256 de la tête (clé applicative)
            $table->string('depot', 500)->nullable();              // copie hors base
            $table->unsignedBigInteger('scelle_par')->nullable();
            $table->timestampTz('scelle_le', 6)->useCurrent();
            $table->index('a_id');
        });

        Schema::create('audit_controles_integrite', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('resultat', 20);                        // conforme | anomalie | non_verifiable
            $table->unsignedBigInteger('de_id')->nullable();
            $table->unsignedBigInteger('a_id')->nullable();
            $table->unsignedInteger('evenements_verifies')->default(0);
            $table->unsignedInteger('scellements_verifies')->default(0);
            $table->json('anomalies')->nullable();
            $table->json('horloge')->nullable();
            $table->unsignedBigInteger('execute_par')->nullable();
            $table->string('declenchement', 20)->default('manuel');
            $table->timestampTz('debut_le', 6);
            $table->timestampTz('fin_le', 6)->nullable();
        });

        Schema::create('audit_exports', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('format', 10);
            $table->string('perimetre', 30);
            $table->json('filtres')->nullable();
            $table->unsignedInteger('nombre')->default(0);
            $table->char('empreinte', 64)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
        });

        Schema::create('audit_archives', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('table_source', 60);
            $table->unsignedBigInteger('de_id');
            $table->unsignedBigInteger('a_id');
            $table->unsignedInteger('nombre');
            $table->string('fichier', 500);
            $table->char('empreinte', 64);
            $table->unsignedBigInteger('scellement_id')->nullable();
            $table->unsignedBigInteger('cree_par')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->creerDeclencheurs();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (array_keys(self::JOURNAUX_PROTEGES) as $table) {
                DB::statement("DROP TRIGGER IF EXISTS trg_journal_append_only ON {$table}");
            }
            DB::statement('DROP FUNCTION IF EXISTS journal_append_only()');
        }

        Schema::dropIfExists('audit_archives');
        Schema::dropIfExists('audit_exports');
        Schema::dropIfExists('audit_controles_integrite');
        Schema::dropIfExists('audit_scellements');
    }

    private function creerDeclencheurs(): void
    {
        // Refuse toute suppression et toute modification, sauf :
        // - la mise à NULL des clés étrangères listées (ON DELETE SET NULL d'un compte ou d'un dossier) ;
        // - une maintenance explicitement autorisée dans la transaction (SET LOCAL gesbudep.audit_maintenance = 'on'),
        //   réservée à la purge réglementée de plages archivées et vérifiées.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION journal_append_only() RETURNS trigger AS $$
DECLARE
    cle text;
    avant jsonb;
    apres jsonb;
BEGIN
    IF coalesce(current_setting('gesbudep.audit_maintenance', true), '') = 'on' THEN
        RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
    END IF;
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Journal % en ajout seul : suppression refusée (id %)', TG_TABLE_NAME, OLD.id
            USING ERRCODE = 'insufficient_privilege';
    END IF;
    avant := to_jsonb(OLD);
    apres := to_jsonb(NEW);
    FOREACH cle IN ARRAY TG_ARGV LOOP
        IF apres -> cle = 'null'::jsonb THEN
            avant := avant - cle;
            apres := apres - cle;
        END IF;
    END LOOP;
    IF avant IS DISTINCT FROM apres THEN
        RAISE EXCEPTION 'Journal % en ajout seul : modification refusée (id %)', TG_TABLE_NAME, OLD.id
            USING ERRCODE = 'insufficient_privilege';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);

        foreach (self::JOURNAUX_PROTEGES as $table => $cles) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $arguments = implode(', ', array_map(fn ($c) => "'{$c}'", $cles));
            DB::statement("DROP TRIGGER IF EXISTS trg_journal_append_only ON {$table}");
            DB::statement("CREATE TRIGGER trg_journal_append_only BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION journal_append_only({$arguments})");
        }
    }
};
