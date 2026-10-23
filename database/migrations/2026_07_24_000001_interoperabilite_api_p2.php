<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase I-P2 — catalogue événements webhooks, retry livraisons, gouvernance. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('libelle');
            $table->string('module', 40);
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('webhooks', function (Blueprint $table) {
            if (! Schema::hasColumn('webhooks', 'connected_app_id')) {
                $table->unsignedBigInteger('connected_app_id')->nullable()->after('id');
                $table->index('connected_app_id');
            }
            if (! Schema::hasColumn('webhooks', 'methode')) {
                $table->string('methode', 10)->default('POST')->after('url');
            }
        });

        Schema::table('webhook_livraisons', function (Blueprint $table) {
            if (! Schema::hasColumn('webhook_livraisons', 'statut')) {
                $table->string('statut', 20)->default('en_attente')->after('evenement');
            }
            if (! Schema::hasColumn('webhook_livraisons', 'tentatives')) {
                $table->unsignedTinyInteger('tentatives')->default(0)->after('succes');
            }
            if (! Schema::hasColumn('webhook_livraisons', 'prochaine_tentative_le')) {
                $table->timestamp('prochaine_tentative_le')->nullable()->after('tentatives');
            }
            if (! Schema::hasColumn('webhook_livraisons', 'erreur')) {
                $table->text('erreur')->nullable()->after('prochaine_tentative_le');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_livraisons', function (Blueprint $table) {
            foreach (['erreur', 'prochaine_tentative_le', 'tentatives', 'statut'] as $col) {
                if (Schema::hasColumn('webhook_livraisons', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('webhooks', function (Blueprint $table) {
            foreach (['methode', 'connected_app_id'] as $col) {
                if (Schema::hasColumn('webhooks', $col)) {
                    if ($col === 'connected_app_id') {
                        $table->dropIndex(['connected_app_id']);
                    }
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('webhook_events');
    }
};
