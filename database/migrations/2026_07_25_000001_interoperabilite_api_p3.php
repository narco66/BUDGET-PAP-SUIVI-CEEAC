<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase I-P3 — métriques API, documentation OpenAPI, audit MOA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('jour');
            $table->string('chemin', 500)->nullable();
            $table->unsignedBigInteger('connected_app_id')->nullable();
            $table->unsignedInteger('appels_total')->default(0);
            $table->unsignedInteger('appels_succes')->default(0);
            $table->unsignedInteger('appels_erreur')->default(0);
            $table->unsignedInteger('appels_rejet')->default(0);
            $table->unsignedInteger('latence_moy_ms')->default(0);
            $table->unsignedInteger('latence_max_ms')->default(0);
            $table->timestamps();

            $table->unique(['jour', 'chemin', 'connected_app_id'], 'api_metrics_jour_chemin_app');
            $table->index(['jour', 'connected_app_id']);
        });

        Schema::create('api_documentations', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20);
            $table->string('titre');
            $table->string('chemin_fichier', 500);
            $table->string('format', 20)->default('openapi');
            $table->boolean('actif')->default(true);
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();

            $table->unique(['version', 'format']);
        });

        Schema::create('api_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('cible_type', 80)->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_audit_logs');
        Schema::dropIfExists('api_documentations');
        Schema::dropIfExists('api_metrics');
    }
};
