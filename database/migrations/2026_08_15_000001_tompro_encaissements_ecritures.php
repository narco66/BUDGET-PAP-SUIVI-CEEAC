<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P2-8 — écritures SYSCOHADA liées aux encaissements (connecteur TOMPRO étendu). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            if (! Schema::hasColumn('ecritures_comptables', 'encaissement_id')) {
                $table->foreignId('encaissement_id')->nullable()->after('paiement_id')
                    ->constrained('encaissements')->nullOnDelete();
                $table->index(['encaissement_id', 'statut']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            if (Schema::hasColumn('ecritures_comptables', 'encaissement_id')) {
                $table->dropConstrainedForeignId('encaissement_id');
            }
        });
    }
};
