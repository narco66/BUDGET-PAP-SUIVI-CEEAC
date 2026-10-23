<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase N-P2 — e-mail institutionnel, logs d'envoi, lien relances recouvrement. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->foreignId('notification_type_id')->nullable()->constrained('notification_types')->nullOnDelete();
            $table->string('libelle');
            $table->string('sujet');
            $table->text('corps_html');
            $table->text('corps_texte')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->foreignId('notification_log_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->foreignId('notification_type_id')->nullable()->constrained('notification_types')->nullOnDelete();
            $table->string('type_code', 80)->nullable();
            $table->string('module', 50)->nullable();
            $table->string('destinataire_email');
            $table->string('destinataire_nom')->nullable();
            $table->string('destinataire_type')->nullable();
            $table->unsignedBigInteger('destinataire_id')->nullable();
            $table->string('sujet');
            $table->text('corps_html')->nullable();
            $table->string('statut', 20)->default('en_attente');
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->unsignedTinyInteger('max_tentatives')->default(3);
            $table->text('erreur')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('envoye_le')->nullable();
            $table->timestamps();

            $table->index(['statut', 'type_code']);
            $table->index('destinataire_email');
        });

        Schema::table('relances_recouvrements', function (Blueprint $table) {
            if (! Schema::hasColumn('relances_recouvrements', 'statut_envoi')) {
                $table->string('statut_envoi', 20)->nullable()->after('observations');
            }
            if (! Schema::hasColumn('relances_recouvrements', 'email_log_id')) {
                $table->foreignId('email_log_id')->nullable()->after('statut_envoi')->constrained('email_logs')->nullOnDelete();
            }
            if (! Schema::hasColumn('relances_recouvrements', 'notification_log_id')) {
                $table->foreignId('notification_log_id')->nullable()->after('email_log_id')->constrained('notification_logs')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('relances_recouvrements', function (Blueprint $table) {
            foreach (['notification_log_id', 'email_log_id', 'statut_envoi'] as $col) {
                if (Schema::hasColumn('relances_recouvrements', $col)) {
                    if (in_array($col, ['email_log_id', 'notification_log_id'], true)) {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });

        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_templates');
    }
};
