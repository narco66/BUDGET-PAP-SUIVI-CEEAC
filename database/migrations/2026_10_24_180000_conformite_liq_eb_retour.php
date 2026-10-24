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

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expressions_besoin MODIFY statut ENUM('brouillon','soumis','valide','rejete','converti','a_corriger') NOT NULL DEFAULT 'brouillon'");
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
