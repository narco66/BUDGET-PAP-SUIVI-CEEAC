<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 — Référentiel RBM/GAR : tables complémentaires et colonnes d'audit/statut.
 * Migration incrémentale idempotente — ne supprime aucune donnée existante.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tablesChaine = ['piliers', 'axes', 'produits', 'sous_produits', 'activites'];

    public function up(): void
    {
        foreach ($this->tablesChaine as $table) {
            $this->ajouterColonnesReferentiel($table);
        }

        // La table taches possède déjà un champ statut (workflow) : colonne distincte pour l'archivage référentiel.
        Schema::table('taches', function (Blueprint $t) {
            if (! Schema::hasColumn('taches', 'statut_referentiel')) {
                $t->enum('statut_referentiel', ['actif', 'inactif', 'archive'])->default('actif')->after('ordre');
            }
            if (! Schema::hasColumn('taches', 'created_by')) {
                $t->foreignId('created_by')->nullable()->after('statut_referentiel')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('taches', 'updated_by')) {
                $t->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('types_indicateurs')) {
            Schema::create('types_indicateurs', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('libelle');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->enum('statut', ['actif', 'inactif', 'archive'])->default('actif');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('unites_mesure')) {
            Schema::create('unites_mesure', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('libelle');
                $table->string('symbole', 20)->nullable();
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->enum('statut', ['actif', 'inactif', 'archive'])->default('actif');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('sources_verification')) {
            Schema::create('sources_verification', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('libelle');
                $table->text('description')->nullable();
                $table->string('url')->nullable();
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->enum('statut', ['actif', 'inactif', 'archive'])->default('actif');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('cadres_logiques')) {
            Schema::create('cadres_logiques', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('intitule');
                $table->text('description')->nullable();
                $table->json('elements')->nullable();
                $table->json('matrice')->nullable();
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->enum('statut', ['actif', 'inactif', 'archive'])->default('actif');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['exercice_id', 'code']);
            });
        } elseif (! Schema::hasColumn('cadres_logiques', 'matrice')) {
            Schema::table('cadres_logiques', function (Blueprint $table) {
                $table->json('matrice')->nullable();
            });
        }

        if (! Schema::hasTable('chaines_resultats')) {
            Schema::create('chaines_resultats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('intitule');
                $table->text('description')->nullable();
                $table->json('elements')->nullable();
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->enum('statut', ['actif', 'inactif', 'archive'])->default('actif');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['exercice_id', 'code']);
            });
        }

        if (! Schema::hasTable('rbm_historiques')) {
            Schema::create('rbm_historiques', function (Blueprint $table) {
                $table->id();
                $table->morphs('historisable');
                $table->string('action', 30);
                $table->json('donnees_avant')->nullable();
                $table->json('donnees_apres')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['historisable_type', 'historisable_id', 'created_at'], 'rbm_historiques_histo_idx');
            });
        }

        Schema::table('indicateurs', function (Blueprint $table) {
            if (! Schema::hasColumn('indicateurs', 'type_indicateur_id')) {
                $table->foreignId('type_indicateur_id')->nullable()->after('type')->constrained('types_indicateurs')->nullOnDelete();
            }
            if (! Schema::hasColumn('indicateurs', 'unite_mesure_id')) {
                $table->foreignId('unite_mesure_id')->nullable()->after('unite')->constrained('unites_mesure')->nullOnDelete();
            }
            if (! Schema::hasColumn('indicateurs', 'statut')) {
                $table->enum('statut', ['actif', 'inactif', 'archive'])->default('actif')->after('seuil_alerte');
            }
            if (! Schema::hasColumn('indicateurs', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('statut')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('indicateurs', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('indicateur_source_verification')) {
            Schema::create('indicateur_source_verification', function (Blueprint $table) {
                $table->id();
                $table->foreignId('indicateur_id')->constrained('indicateurs')->cascadeOnDelete();
                $table->foreignId('source_verification_id')->constrained('sources_verification')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['indicateur_id', 'source_verification_id'], 'indicateur_source_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('indicateur_source_verification');

        Schema::table('indicateurs', function (Blueprint $table) {
            foreach (['updated_by', 'created_by', 'statut', 'unite_mesure_id', 'type_indicateur_id'] as $col) {
                if (Schema::hasColumn('indicateurs', $col)) {
                    if (str_ends_with($col, '_id')) {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });

        Schema::dropIfExists('rbm_historiques');
        Schema::dropIfExists('chaines_resultats');
        Schema::dropIfExists('cadres_logiques');
        Schema::dropIfExists('sources_verification');
        Schema::dropIfExists('unites_mesure');
        Schema::dropIfExists('types_indicateurs');

        foreach ($this->tablesChaine as $table) {
            $this->retirerColonnesReferentiel($table);
        }

        Schema::table('taches', function (Blueprint $t) {
            foreach (['updated_by', 'created_by', 'statut_referentiel'] as $col) {
                if (Schema::hasColumn('taches', $col)) {
                    if (str_ends_with($col, '_by')) {
                        $t->dropConstrainedForeignId($col);
                    } else {
                        $t->dropColumn($col);
                    }
                }
            }
        });
    }

    private function ajouterColonnesReferentiel(string $table): void
    {
        Schema::table($table, function (Blueprint $t) use ($table) {
            if (! Schema::hasColumn($table, 'statut')) {
                $t->enum('statut', ['actif', 'inactif', 'archive'])->default('actif')->after('ordre');
            }
            if (! Schema::hasColumn($table, 'created_by')) {
                $t->foreignId('created_by')->nullable()->after('statut')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn($table, 'updated_by')) {
                $t->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });
    }

    private function retirerColonnesReferentiel(string $table): void
    {
        Schema::table($table, function (Blueprint $t) use ($table) {
            foreach (['updated_by', 'created_by', 'statut'] as $col) {
                if (Schema::hasColumn($table, $col)) {
                    if (str_ends_with($col, '_by')) {
                        $t->dropConstrainedForeignId($col);
                    } else {
                        $t->dropColumn($col);
                    }
                }
            }
        });
    }
};
