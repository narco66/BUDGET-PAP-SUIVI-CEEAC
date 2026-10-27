<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phase D0 — renforcement chaîne de la dépense (activité, index). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('engagements', 'activite_id')) {
            Schema::table('engagements', function (Blueprint $table) {
                $table->foreignId('activite_id')->nullable()->after('expression_besoin_id')
                    ->constrained('activites')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('expressions_besoin', 'ligne_budgetaire_id')
            && Schema::hasColumn('activites', 'ligne_budgetaire_id')) {
            $besoins = DB::table('expressions_besoin as eb')
                ->join('activites as a', 'a.id', '=', 'eb.activite_id')
                ->whereNull('eb.ligne_budgetaire_id')
                ->whereNotNull('a.ligne_budgetaire_id')
                ->select('eb.id', 'a.ligne_budgetaire_id')
                ->get();

            foreach ($besoins as $besoin) {
                DB::table('expressions_besoin')
                    ->where('id', $besoin->id)
                    ->update(['ligne_budgetaire_id' => $besoin->ligne_budgetaire_id]);
            }
        }

        if (Schema::hasColumn('engagements', 'activite_id')) {
            $engagements = DB::table('engagements as e')
                ->join('expressions_besoin as eb', 'eb.id', '=', 'e.expression_besoin_id')
                ->whereNull('e.activite_id')
                ->whereNotNull('eb.activite_id')
                ->select('e.id', 'eb.activite_id')
                ->get();

            foreach ($engagements as $engagement) {
                DB::table('engagements')
                    ->where('id', $engagement->id)
                    ->update(['activite_id' => $engagement->activite_id]);
            }

            $engagementsSansActivite = DB::table('engagements')->whereNull('activite_id')->pluck('id');
            foreach ($engagementsSansActivite as $engagementId) {
                $activiteId = DB::table('engagement_imputations as ei')
                    ->join('lignes_budgetaires as lb', 'lb.id', '=', 'ei.ligne_budgetaire_id')
                    ->where('ei.engagement_id', $engagementId)
                    ->whereNotNull('lb.activite_id')
                    ->value('lb.activite_id');

                if ($activiteId) {
                    DB::table('engagements')->where('id', $engagementId)->update(['activite_id' => $activiteId]);
                }
            }
        }

        Schema::table('engagements', function (Blueprint $table) {
            if (! $this->indexExists('engagements', 'engagements_exercice_statut_index')) {
                $table->index(['exercice_id', 'statut'], 'engagements_exercice_statut_index');
            }
        });

        Schema::table('expressions_besoin', function (Blueprint $table) {
            if (! $this->indexExists('expressions_besoin', 'expressions_besoin_exercice_statut_index')) {
                $table->index(['exercice_id', 'statut'], 'expressions_besoin_exercice_statut_index');
            }
            if (! $this->indexExists('expressions_besoin', 'expressions_besoin_activite_index')) {
                $table->index('activite_id', 'expressions_besoin_activite_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            if ($this->indexExists('engagements', 'engagements_exercice_statut_index')) {
                $table->dropIndex('engagements_exercice_statut_index');
            }
            if (Schema::hasColumn('engagements', 'activite_id')) {
                $table->dropConstrainedForeignId('activite_id');
            }
        });

        Schema::table('expressions_besoin', function (Blueprint $table) {
            if ($this->indexExists('expressions_besoin', 'expressions_besoin_exercice_statut_index')) {
                $table->dropIndex('expressions_besoin_exercice_statut_index');
            }
            if ($this->indexExists('expressions_besoin', 'expressions_besoin_activite_index')) {
                $table->dropIndex('expressions_besoin_activite_index');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index);
    }
};
