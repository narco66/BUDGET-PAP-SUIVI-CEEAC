<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase N-P3 — SMS, canaux, escalades, audit MOA, préférences utilisateur. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_type_id')->constrained('notification_types')->cascadeOnDelete();
            $table->string('canal', 20);
            $table->boolean('actif')->default(true);
            $table->unsignedTinyInteger('ordre')->default(1);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['notification_type_id', 'canal']);
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->foreignId('notification_type_id')->nullable()->constrained('notification_types')->nullOnDelete();
            $table->string('libelle');
            $table->string('corps', 320);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_template_id')->nullable()->constrained('sms_templates')->nullOnDelete();
            $table->foreignId('notification_log_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->string('type_code', 80)->nullable();
            $table->string('module', 50)->nullable();
            $table->string('destinataire_telephone', 30);
            $table->string('destinataire_type')->nullable();
            $table->unsignedBigInteger('destinataire_id')->nullable();
            $table->string('message', 320);
            $table->string('statut', 20)->default('en_attente');
            $table->string('provider', 30)->default('log');
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->unsignedTinyInteger('max_tentatives')->default(3);
            $table->text('erreur')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('envoye_le')->nullable();
            $table->timestamps();

            $table->index(['statut', 'type_code']);
        });

        Schema::create('audit_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('action', 40);
            $table->foreignId('notification_log_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->foreignId('alerte_id')->nullable()->constrained('alertes')->nullOnDelete();
            $table->string('type_code', 80)->nullable();
            $table->string('module', 50)->nullable();
            $table->string('canal', 20)->nullable();
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('destinataire_type')->nullable();
            $table->unsignedBigInteger('destinataire_id')->nullable();
            $table->unsignedTinyInteger('escalade_niveau')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['action', 'occurred_at']);
            $table->index(['module', 'type_code']);
        });

        Schema::table('alertes', function (Blueprint $table) {
            if (! Schema::hasColumn('alertes', 'escalade_niveau')) {
                $table->unsignedTinyInteger('escalade_niveau')->default(0)->after('statut');
            }
            if (! Schema::hasColumn('alertes', 'derniere_escalade_le')) {
                $table->timestamp('derniere_escalade_le')->nullable()->after('escalade_niveau');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'preferences_notifications')) {
                $table->json('preferences_notifications')->nullable()->after('telephone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'preferences_notifications')) {
                $table->dropColumn('preferences_notifications');
            }
        });

        Schema::table('alertes', function (Blueprint $table) {
            foreach (['derniere_escalade_le', 'escalade_niveau'] as $col) {
                if (Schema::hasColumn('alertes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('audit_notifications');
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('notification_channels');
    }
};
