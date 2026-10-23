<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phase S4 — suivi physique : quantités, mensuel, consolidation produits, alertes, audit. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campagnes_suivi') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE campagnes_suivi MODIFY COLUMN type ENUM('mensuel','trimestriel','semestriel','annuel','pluriannuel') NOT NULL DEFAULT 'trimestriel'");
        }

        Schema::table('collectes', function (Blueprint $table) {
            if (! Schema::hasColumn('collectes', 'quantite_prevue')) {
                $table->decimal('quantite_prevue', 18, 4)->nullable()->after('taux_physique');
            }
            if (! Schema::hasColumn('collectes', 'quantite_realisee')) {
                $table->decimal('quantite_realisee', 18, 4)->nullable()->after('quantite_prevue');
            }
            if (! Schema::hasColumn('collectes', 'unite')) {
                $table->string('unite', 50)->nullable()->after('quantite_realisee');
            }
            if (! Schema::hasColumn('collectes', 'date_realisation')) {
                $table->date('date_realisation')->nullable()->after('unite');
            }
        });

        if (! Schema::hasTable('realisation_produits')) {
            Schema::create('realisation_produits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campagne_id')->constrained('campagnes_suivi')->cascadeOnDelete();
                $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
                $table->decimal('taux_physique', 5, 2)->default(0);
                $table->decimal('taux_financier', 5, 2)->default(0);
                $table->unsignedInteger('nb_activites')->default(0);
                $table->unsignedInteger('nb_collectes_validees')->default(0);
                $table->timestamps();

                $table->unique(['campagne_id', 'produit_id']);
            });
        }

        if (! Schema::hasTable('alertes_performance')) {
            Schema::create('alertes_performance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
                $table->foreignId('campagne_id')->nullable()->constrained('campagnes_suivi')->nullOnDelete();
                $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();
                $table->foreignId('produit_id')->nullable()->constrained('produits')->nullOnDelete();
                $table->string('type', 60);
                $table->enum('gravite', ['faible', 'moyenne', 'elevee'])->default('moyenne');
                $table->string('reference')->nullable();
                $table->text('message');
                $table->boolean('resolu')->default(false);
                $table->timestamp('detecte_le');
                $table->timestamps();

                $table->index(['exercice_id', 'resolu']);
            });
        }

        if (! Schema::hasTable('audit_suivi_physique')) {
            Schema::create('audit_suivi_physique', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
                $table->foreignId('auditeur_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedTinyInteger('score_conformite')->default(0);
                $table->json('rapport');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_suivi_physique');
        Schema::dropIfExists('alertes_performance');
        Schema::dropIfExists('realisation_produits');

        Schema::table('collectes', function (Blueprint $table) {
            foreach (['date_realisation', 'unite', 'quantite_realisee', 'quantite_prevue'] as $col) {
                if (Schema::hasColumn('collectes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('campagnes_suivi') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE campagnes_suivi MODIFY COLUMN type ENUM('trimestriel','semestriel','annuel','pluriannuel') NOT NULL DEFAULT 'trimestriel'");
        }
    }
};
