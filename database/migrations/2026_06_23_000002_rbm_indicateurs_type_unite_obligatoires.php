<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E3 — type_indicateur_id et unite_mesure_id obligatoires sur les indicateurs IOV.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->assurerCataloguesParDefaut();
        $this->backfillTypes();
        $this->backfillUnites();

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('indicateurs', function (Blueprint $table) {
                $table->dropForeign(['type_indicateur_id']);
                $table->dropForeign(['unite_mesure_id']);
            });
        }

        Schema::table('indicateurs', function (Blueprint $table) {
            $table->foreignId('type_indicateur_id')->nullable(false)->change();
            $table->foreignId('unite_mesure_id')->nullable(false)->change();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('indicateurs', function (Blueprint $table) {
                $table->foreign('type_indicateur_id')->references('id')->on('types_indicateurs')->restrictOnDelete();
                $table->foreign('unite_mesure_id')->references('id')->on('unites_mesure')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('indicateurs', function (Blueprint $table) {
                $table->dropForeign(['type_indicateur_id']);
                $table->dropForeign(['unite_mesure_id']);
            });
        }

        Schema::table('indicateurs', function (Blueprint $table) {
            $table->foreignId('type_indicateur_id')->nullable()->change();
            $table->foreignId('unite_mesure_id')->nullable()->change();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('indicateurs', function (Blueprint $table) {
                $table->foreign('type_indicateur_id')->references('id')->on('types_indicateurs')->nullOnDelete();
                $table->foreign('unite_mesure_id')->references('id')->on('unites_mesure')->nullOnDelete();
            });
        }
    }

    private function assurerCataloguesParDefaut(): void
    {
        $types = [
            ['code' => 'STR', 'libelle' => 'Stratégique', 'ordre' => 1],
            ['code' => 'OPE', 'libelle' => 'Opérationnel', 'ordre' => 2],
            ['code' => 'FIN', 'libelle' => 'Financier', 'ordre' => 3],
            ['code' => 'IMP', 'libelle' => 'Impact', 'ordre' => 4],
        ];

        foreach ($types as $t) {
            if (! DB::table('types_indicateurs')->where('code', $t['code'])->exists()) {
                DB::table('types_indicateurs')->insert($t + [
                    'statut' => 'actif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $unites = [
            ['code' => 'PCT', 'libelle' => 'Pourcentage', 'symbole' => '%', 'ordre' => 1],
            ['code' => 'NB', 'libelle' => 'Nombre', 'symbole' => 'u.', 'ordre' => 2],
            ['code' => 'XAF', 'libelle' => 'Franc CFA', 'symbole' => 'XAF', 'ordre' => 3],
            ['code' => 'JOUR', 'libelle' => 'Jours', 'symbole' => 'j', 'ordre' => 4],
        ];

        foreach ($unites as $u) {
            if (! DB::table('unites_mesure')->where('code', $u['code'])->exists()) {
                DB::table('unites_mesure')->insert($u + [
                    'statut' => 'actif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function backfillTypes(): void
    {
        $mapping = [
            'strategique' => 'STR',
            'operationnel' => 'OPE',
            'financier' => 'FIN',
            'impact' => 'IMP',
        ];

        foreach ($mapping as $typeEnum => $code) {
            $typeId = DB::table('types_indicateurs')->where('code', $code)->value('id');
            if ($typeId) {
                DB::table('indicateurs')
                    ->where('type', $typeEnum)
                    ->whereNull('type_indicateur_id')
                    ->update(['type_indicateur_id' => $typeId]);
            }
        }

        $defaut = DB::table('types_indicateurs')->where('code', 'OPE')->value('id');
        if ($defaut) {
            DB::table('indicateurs')->whereNull('type_indicateur_id')->update(['type_indicateur_id' => $defaut]);
        }
    }

    private function backfillUnites(): void
    {
        $defautNb = DB::table('unites_mesure')->where('code', 'NB')->value('id');
        $defautPct = DB::table('unites_mesure')->where('code', 'PCT')->value('id');

        DB::table('indicateurs')->whereNull('unite_mesure_id')->whereNull('unite')->update([
            'unite_mesure_id' => $defautNb,
        ]);

        foreach (DB::table('unites_mesure')->get(['id', 'code', 'symbole']) as $unite) {
            DB::table('indicateurs')
                ->whereNull('unite_mesure_id')
                ->where(function ($q) use ($unite) {
                    $q->where('unite', $unite->symbole)->orWhere('unite', $unite->code);
                })
                ->update(['unite_mesure_id' => $unite->id]);
        }

        if ($defautPct) {
            DB::table('indicateurs')->whereNull('unite_mesure_id')->update(['unite_mesure_id' => $defautPct]);
        }
    }
};
