<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P0 Reporting — période paramétrable, empreinte sources, verrou post-signature. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapports', function (Blueprint $table) {
            if (! Schema::hasColumn('rapports', 'periode_type')) {
                $table->string('periode_type', 20)->default('annuel')->after('periode');
            }
            if (! Schema::hasColumn('rapports', 'periode_debut')) {
                $table->date('periode_debut')->nullable()->after('periode_type');
            }
            if (! Schema::hasColumn('rapports', 'periode_fin')) {
                $table->date('periode_fin')->nullable()->after('periode_debut');
            }
            if (! Schema::hasColumn('rapports', 'checksum_sources')) {
                $table->char('checksum_sources', 64)->nullable()->after('hash');
            }
            if (! Schema::hasColumn('rapports', 'verrouille')) {
                $table->boolean('verrouille')->default(false)->after('statut');
            }
        });

        Schema::table('rapports', function (Blueprint $table) {
            $table->index(['type', 'exercice_id', 'periode', 'format'], 'rapports_type_exercice_periode_format_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rapports', function (Blueprint $table) {
            $table->dropIndex('rapports_type_exercice_periode_format_idx');
            $table->dropColumn(['periode_type', 'periode_debut', 'periode_fin', 'checksum_sources', 'verrouille']);
        });
    }
};
