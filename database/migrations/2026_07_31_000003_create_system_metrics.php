<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Administration Technique — Lot T1 (centre de supervision).
 * `system_metrics` : instantanés horodatés de l'état de santé technique (disque, files, jobs échoués,
 * sessions, connexions). Permet l'analyse historique et atteste que la capture planifiée tourne.
 * NON DESTRUCTIF (création conditionnelle).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_metrics')) {
            Schema::create('system_metrics', function (Blueprint $t) {
                $t->id();
                $t->timestamp('capture_le')->index();
                $t->string('statut_global', 20)->default('ok'); // ok|warning|critique
                $t->decimal('disque_libre_pct', 5, 2)->nullable();
                $t->unsignedBigInteger('disque_libre_octets')->nullable();
                $t->unsignedInteger('jobs_en_attente')->default(0);
                $t->unsignedInteger('jobs_echoues')->default(0);
                $t->unsignedInteger('sessions_actives')->default(0);
                $t->unsignedInteger('echecs_connexion_24h')->default(0);
                $t->decimal('charge_systeme', 6, 2)->nullable();
                $t->json('details')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
    }
};
