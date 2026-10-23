<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase N-P0 — référentiel notifications, templates, journal, enrichissement centre. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('module', 50);
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->string('criticite_defaut', 20)->default('information');
            $table->string('canal_defaut', 20)->default('database');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['module', 'actif']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_type_id')->constrained('notification_types')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('canal', 20)->default('database');
            $table->string('sujet')->nullable();
            $table->text('corps');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['notification_type_id', 'code', 'canal']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_type_id')->nullable()->constrained('notification_types')->nullOnDelete();
            $table->string('type_code', 80)->nullable();
            $table->string('module', 50)->nullable();
            $table->string('criticite', 20)->default('information');
            $table->string('canal', 20)->default('database');
            $table->string('destinataire_type')->nullable();
            $table->unsignedBigInteger('destinataire_id')->nullable();
            $table->foreignId('emetteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titre');
            $table->text('message');
            $table->string('url', 500)->nullable();
            $table->string('statut', 20)->default('envoye');
            $table->json('metadata')->nullable();
            $table->uuid('laravel_notification_id')->nullable();
            $table->timestamp('envoye_le')->nullable();
            $table->timestamps();

            $table->index(['module', 'type_code']);
            $table->index(['destinataire_type', 'destinataire_id']);
            $table->index('envoye_le');
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'type_code')) {
                $table->string('type_code', 80)->nullable()->after('type');
            }
            if (! Schema::hasColumn('notifications', 'module')) {
                $table->string('module', 50)->nullable()->after('type_code');
            }
            if (! Schema::hasColumn('notifications', 'criticite')) {
                $table->string('criticite', 20)->nullable()->after('module');
            }
            if (! Schema::hasColumn('notifications', 'archive_at')) {
                $table->timestamp('archive_at')->nullable()->after('read_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            foreach (['type_code', 'module', 'criticite', 'archive_at'] as $col) {
                if (Schema::hasColumn('notifications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_types');
    }
};
