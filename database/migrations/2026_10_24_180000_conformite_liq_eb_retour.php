<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            if (! Schema::hasColumn('liquidations', 'numero_tranche')) {
                $table->unsignedSmallInteger('numero_tranche')->nullable();
            }
            if (! Schema::hasColumn('liquidations', 'numero_facture')) {
                $table->string('numero_facture', 100)->nullable();
            }
            if (! Schema::hasColumn('liquidations', 'date_facture')) {
                $table->date('date_facture')->nullable();
            }
            if (! Schema::hasColumn('liquidations', 'fournisseur')) {
                $table->string('fournisseur', 180)->nullable();
            }
        });

        $this->elargirStatutBesoin([
            'brouillon', 'soumis', 'valide', 'rejete', 'converti', 'a_corriger',
        ]);
    }

    /** @param list<string> $valeurs */
    private function elargirStatutBesoin(array $valeurs): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $liste = implode(',', array_map(fn (string $v) => "'{$v}'", $valeurs));

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE expressions_besoin MODIFY statut ENUM({$liste}) NOT NULL DEFAULT 'brouillon'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE expressions_besoin DROP CONSTRAINT IF EXISTS expressions_besoin_statut_check');
            DB::statement("ALTER TABLE expressions_besoin ADD CONSTRAINT expressions_besoin_statut_check CHECK (statut::text = ANY (ARRAY[{$liste}]::text[]))");
        }
    }

    public function down(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            foreach (['numero_tranche', 'numero_facture', 'date_facture', 'fournisseur'] as $col) {
                if (Schema::hasColumn('liquidations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
