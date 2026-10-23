<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P5 — index performance historique PAP et registre risques. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pap_historiques')) {
            Schema::table('pap_historiques', function (Blueprint $table) {
                $table->index(['pap_id', 'version'], 'pap_historiques_pap_version_idx');
                $table->index(['pap_id', 'action'], 'pap_historiques_pap_action_idx');
            });
        }

        if (Schema::hasTable('pap_risques')) {
            Schema::table('pap_risques', function (Blueprint $table) {
                $table->index(['pap_id', 'statut'], 'pap_risques_pap_statut_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pap_historiques')) {
            Schema::table('pap_historiques', function (Blueprint $table) {
                $table->dropIndex('pap_historiques_pap_version_idx');
                $table->dropIndex('pap_historiques_pap_action_idx');
            });
        }

        if (Schema::hasTable('pap_risques')) {
            Schema::table('pap_risques', function (Blueprint $table) {
                $table->dropIndex('pap_risques_pap_statut_idx');
            });
        }
    }
};
