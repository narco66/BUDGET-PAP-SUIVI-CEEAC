<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E2 — intégrité RBM : sous_produit_id obligatoire, unicité codes activité/tâche.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rattacherActivitesOrphelines();
        $this->dedupliquerCodes('activites', 'sous_produit_id');
        $this->dedupliquerCodes('taches', 'activite_id');

        Schema::table('activites', function (Blueprint $table) {
            $table->foreignId('sous_produit_id')->nullable(false)->change();
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->unique(['sous_produit_id', 'code'], 'activites_sous_produit_code_unique');
        });

        Schema::table('taches', function (Blueprint $table) {
            $table->unique(['activite_id', 'code'], 'taches_activite_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('activites', function (Blueprint $table) {
            $table->foreignId('sous_produit_id')->nullable()->change();
            $table->dropUnique('activites_sous_produit_code_unique');
        });

        Schema::table('taches', function (Blueprint $table) {
            $table->dropUnique('taches_activite_code_unique');
        });
    }

    /** Rattache les activités sans sous-produit à un sous-produit technique du produit. */
    private function rattacherActivitesOrphelines(): void
    {
        $orphelines = DB::table('activites')
            ->whereNull('sous_produit_id')
            ->orderBy('id')
            ->get(['id', 'produit_id', 'code']);

        foreach ($orphelines as $act) {
            if (! $act->produit_id) {
                continue;
            }

            $spId = DB::table('sous_produits')
                ->where('produit_id', $act->produit_id)
                ->where('code', 'SP-MIG')
                ->value('id');

            if (! $spId) {
                $spId = DB::table('sous_produits')->insertGetId([
                    'produit_id' => $act->produit_id,
                    'code' => 'SP-MIG',
                    'intitule' => 'Sous-produit (rattachement migration)',
                    'statut' => 'actif',
                    'ordre' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('activites')->where('id', $act->id)->update(['sous_produit_id' => $spId]);
        }

        $fallbackSp = DB::table('sous_produits')->orderBy('id')->value('id');
        if ($fallbackSp) {
            DB::table('activites')->whereNull('sous_produit_id')->update(['sous_produit_id' => $fallbackSp]);
        }
    }

    /** Suffixe les doublons de code au sein d'un même parent avant contrainte unique. */
    private function dedupliquerCodes(string $table, string $parentCol): void
    {
        $doublons = DB::table($table)
            ->select($parentCol, 'code', DB::raw('COUNT(*) as total'))
            ->whereNotNull($parentCol)
            ->groupBy($parentCol, 'code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($doublons as $groupe) {
            $ids = DB::table($table)
                ->where($parentCol, $groupe->{$parentCol})
                ->where('code', $groupe->code)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids->slice(1)->values() as $i => $id) {
                DB::table($table)->where('id', $id)->update([
                    'code' => $groupe->code.'-DUP'.($i + 1),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
