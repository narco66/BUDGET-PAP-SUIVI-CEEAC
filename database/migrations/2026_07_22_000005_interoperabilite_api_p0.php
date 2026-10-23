<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase I-P0 — enrichissement journal API (traçabilité interopérabilité). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('api_logs', 'connected_app_id')) {
                $table->unsignedBigInteger('connected_app_id')->nullable()->after('token_nom');
                $table->index('connected_app_id');
            }
            if (! Schema::hasColumn('api_logs', 'request_hash')) {
                $table->char('request_hash', 64)->nullable()->after('chemin');
            }
            if (! Schema::hasColumn('api_logs', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip');
            }
            if (! Schema::hasColumn('api_logs', 'statut_metier')) {
                $table->string('statut_metier', 20)->nullable()->after('statut');
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            foreach (['statut_metier', 'user_agent', 'request_hash', 'connected_app_id'] as $col) {
                if (Schema::hasColumn('api_logs', $col)) {
                    if ($col === 'connected_app_id') {
                        $table->dropIndex(['connected_app_id']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
