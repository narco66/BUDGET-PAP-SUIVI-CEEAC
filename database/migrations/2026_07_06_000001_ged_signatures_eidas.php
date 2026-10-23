<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P3-6 — Signature qualifiée GED (eIDAS) — métadonnées prestataire. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ged_signatures')) {
            return;
        }

        Schema::table('ged_signatures', function (Blueprint $table) {
            if (! Schema::hasColumn('ged_signatures', 'provider')) {
                $table->string('provider', 20)->default('interne')->after('statut');
            }
            if (! Schema::hasColumn('ged_signatures', 'niveau')) {
                $table->string('niveau', 20)->default('interne')->after('provider');
            }
            if (! Schema::hasColumn('ged_signatures', 'externe_id')) {
                $table->string('externe_id', 100)->nullable()->after('niveau');
            }
            if (! Schema::hasColumn('ged_signatures', 'horodatage_tsa')) {
                $table->timestamp('horodatage_tsa')->nullable()->after('horodatage');
            }
            if (! Schema::hasColumn('ged_signatures', 'token_verification')) {
                $table->string('token_verification', 64)->nullable()->after('horodatage_tsa');
            }
            if (! Schema::hasColumn('ged_signatures', 'metadonnees_prestataire')) {
                $table->json('metadonnees_prestataire')->nullable()->after('token_verification');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ged_signatures')) {
            return;
        }

        Schema::table('ged_signatures', function (Blueprint $table) {
            foreach (['provider', 'niveau', 'externe_id', 'horodatage_tsa', 'token_verification', 'metadonnees_prestataire'] as $col) {
                if (Schema::hasColumn('ged_signatures', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
