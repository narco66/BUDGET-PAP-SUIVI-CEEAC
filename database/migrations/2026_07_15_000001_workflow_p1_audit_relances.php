<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase W-P1 — journal audit workflow, échéances et relances SLA. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_workflow')) {
            Schema::create('audit_workflow', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
                $table->string('workflow_code', 30)->nullable();
                $table->string('objet_type')->nullable();
                $table->unsignedBigInteger('objet_id')->nullable();
                $table->string('action', 40);
                $table->unsignedSmallInteger('etape_ordre')->nullable();
                $table->foreignId('acteur_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('observation')->nullable();
                $table->string('categorie_rejet', 30)->nullable();
                $table->json('meta')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        Schema::table('workflow_instance_etapes', function (Blueprint $table) {
            if (! Schema::hasColumn('workflow_instance_etapes', 'echeance_le')) {
                $table->timestamp('echeance_le')->nullable()->after('categorie_rejet');
            }
            if (! Schema::hasColumn('workflow_instance_etapes', 'derniere_relance_le')) {
                $table->timestamp('derniere_relance_le')->nullable()->after('echeance_le');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instance_etapes', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_instance_etapes', 'derniere_relance_le')) {
                $table->dropColumn('derniere_relance_le');
            }
            if (Schema::hasColumn('workflow_instance_etapes', 'echeance_le')) {
                $table->dropColumn('echeance_le');
            }
        });
        Schema::dropIfExists('audit_workflow');
    }
};
