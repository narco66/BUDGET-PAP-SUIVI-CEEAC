<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase W-P2 — visas électroniques, traçabilité étapes, rejet collecte. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visas_electroniques')) {
            Schema::create('visas_electroniques', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_instance_id')->constrained('workflow_instances')->cascadeOnDelete();
                $table->foreignId('workflow_instance_etape_id')->constrained('workflow_instance_etapes')->cascadeOnDelete();
                $table->string('type_visa', 20);
                $table->foreignId('acteur_id')->constrained('users');
                $table->string('hash_contenu', 64);
                $table->text('certificat')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        Schema::table('workflow_instance_etapes', function (Blueprint $table) {
            if (! Schema::hasColumn('workflow_instance_etapes', 'ip')) {
                $table->string('ip', 45)->nullable()->after('derniere_relance_le');
            }
            if (! Schema::hasColumn('workflow_instance_etapes', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip');
            }
            if (! Schema::hasColumn('workflow_instance_etapes', 'hash_visa')) {
                $table->string('hash_visa', 64)->nullable()->after('user_agent');
            }
        });

        if (Schema::hasTable('collectes')) {
            Schema::table('collectes', function (Blueprint $table) {
                if (! Schema::hasColumn('collectes', 'motif_rejet')) {
                    $table->text('motif_rejet')->nullable()->after('valide_par');
                }
                if (! Schema::hasColumn('collectes', 'categorie_rejet')) {
                    $table->string('categorie_rejet', 30)->nullable()->after('motif_rejet');
                }
            });
        }

        if (Schema::hasTable('plans_strategiques') && ! Schema::hasColumn('plans_strategiques', 'categorie_rejet')) {
            Schema::table('plans_strategiques', function (Blueprint $table) {
                $table->string('categorie_rejet', 30)->nullable()->after('motif_rejet');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plans_strategiques') && Schema::hasColumn('plans_strategiques', 'categorie_rejet')) {
            Schema::table('plans_strategiques', fn (Blueprint $t) => $t->dropColumn('categorie_rejet'));
        }
        if (Schema::hasTable('collectes')) {
            Schema::table('collectes', fn (Blueprint $t) => $t->dropColumn(['motif_rejet', 'categorie_rejet']));
        }
        Schema::table('workflow_instance_etapes', function (Blueprint $t) {
            foreach (['hash_visa', 'user_agent', 'ip'] as $col) {
                if (Schema::hasColumn('workflow_instance_etapes', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('visas_electroniques');
    }
};
