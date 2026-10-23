<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase W-P0 — motifs rejet, catégories, circuit encaissements. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('motifs_rejet')) {
            Schema::create('motifs_rejet', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('categorie', 30);
                $table->string('libelle');
                $table->boolean('actif')->default(true);
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('workflow_instance_etapes', function (Blueprint $table) {
            if (! Schema::hasColumn('workflow_instance_etapes', 'categorie_rejet')) {
                $table->string('categorie_rejet', 30)->nullable()->after('observation');
            }
        });

        Schema::table('encaissements', function (Blueprint $table) {
            if (! Schema::hasColumn('encaissements', 'motif_rejet')) {
                $table->text('motif_rejet')->nullable()->after('observations');
            }
            if (! Schema::hasColumn('encaissements', 'categorie_rejet')) {
                $table->string('categorie_rejet', 30)->nullable()->after('motif_rejet');
            }
        });

        Schema::table('paps', function (Blueprint $table) {
            if (! Schema::hasColumn('paps', 'categorie_rejet')) {
                $table->string('categorie_rejet', 30)->nullable()->after('motif_rejet');
            }
        });

        if (Schema::hasTable('budgets')) {
            Schema::table('budgets', function (Blueprint $table) {
                if (! Schema::hasColumn('budgets', 'categorie_rejet')) {
                    $table->string('categorie_rejet', 30)->nullable()->after('motif_rejet');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('budgets') && Schema::hasColumn('budgets', 'categorie_rejet')) {
            Schema::table('budgets', fn (Blueprint $t) => $t->dropColumn('categorie_rejet'));
        }
        Schema::table('paps', fn (Blueprint $t) => $t->dropColumn('categorie_rejet'));
        Schema::table('encaissements', function (Blueprint $t) {
            $t->dropColumn(['motif_rejet', 'categorie_rejet']);
        });
        Schema::table('workflow_instance_etapes', fn (Blueprint $t) => $t->dropColumn('categorie_rejet'));
        Schema::dropIfExists('motifs_rejet');
    }
};
