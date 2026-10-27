<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index de performance des vues (listes, traçabilité, GED) : clés étrangères et colonnes filtrées qui n'étaient
 * pas indexées (PostgreSQL ne crée pas d'index sur les clés étrangères). Ajout seul, idempotent, sans perte.
 */
return new class extends Migration
{
    /** @var array<string, list<list<string>>> table => index (liste de colonnes) */
    private const INDEX = [
        'audit_workflow' => [['objet_type', 'objet_id'], ['workflow_instance_id'], ['workflow_code', 'created_at']],
        'workflow_instance_etapes' => [['instance_id', 'ordre']],
        'liquidations' => [['engagement_id']],
        'ordonnancements' => [['liquidation_id']],
        'paiements' => [['ordonnancement_id']],
        'engagement_imputations' => [['engagement_id']],
        'ged_partages' => [['document_id'], ['partage_avec']],
        'lignes_budgetaires' => [['activite_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEX as $table => $index) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($index as $colonnes) {
                if (array_diff($colonnes, Schema::getColumnListing($table)) !== [] || Schema::hasIndex($table, $colonnes)) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($colonnes, $this->nom($table, $colonnes)));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEX as $table => $index) {
            foreach ($index as $colonnes) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $this->nom($table, $colonnes))) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($this->nom($table, $colonnes)));
                }
            }
        }
    }

    private function nom(string $table, array $colonnes): string
    {
        return 'perf_'.$table.'_'.implode('_', $colonnes).'_idx';
    }
};
