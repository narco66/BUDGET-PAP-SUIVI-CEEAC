<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase W-P3 — versionning, branches conditionnelles, graphe de transitions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            if (! Schema::hasColumn('workflows', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('actif');
            }
            if (! Schema::hasColumn('workflows', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('version');
            }
            if (! Schema::hasColumn('workflows', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('workflow_versions')) {
            Schema::create('workflow_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
                $table->unsignedInteger('numero');
                $table->string('libelle')->nullable();
                $table->string('statut', 20)->default('publie');
                $table->json('snapshot_json')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['workflow_id', 'numero']);
            });
        }

        if (! Schema::hasTable('workflow_conditions')) {
            Schema::create('workflow_conditions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
                $table->foreignId('workflow_version_id')->nullable()->constrained('workflow_versions')->nullOnDelete();
                $table->string('code', 50);
                $table->string('libelle');
                $table->json('expression');
                $table->boolean('actif')->default(true);
                $table->timestamps();

                $table->unique(['workflow_id', 'code']);
            });
        }

        if (! Schema::hasTable('workflow_transitions')) {
            Schema::create('workflow_transitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
                $table->foreignId('workflow_version_id')->nullable()->constrained('workflow_versions')->nullOnDelete();
                $table->unsignedInteger('etape_source_ordre');
                $table->unsignedInteger('etape_cible_ordre')->nullable();
                $table->foreignId('condition_id')->nullable()->constrained('workflow_conditions')->nullOnDelete();
                $table->string('type', 20)->default('sequentiel');
                $table->string('libelle')->nullable();
                $table->unsignedInteger('priorite')->default(0);
                $table->timestamps();

                $table->index(['workflow_id', 'etape_source_ordre']);
            });
        }

        Schema::table('workflow_instances', function (Blueprint $table) {
            if (! Schema::hasColumn('workflow_instances', 'workflow_version_id')) {
                $table->foreignId('workflow_version_id')->nullable()->after('workflow_id')->constrained('workflow_versions')->nullOnDelete();
            }
            if (! Schema::hasColumn('workflow_instances', 'objet_version')) {
                $table->string('objet_version', 50)->nullable()->after('objet_id');
            }
        });

        Schema::table('workflow_etapes', function (Blueprint $table) {
            if (! Schema::hasColumn('workflow_etapes', 'type_approbation')) {
                $table->string('type_approbation', 20)->default('sequentiel')->after('role');
            }
            if (! Schema::hasColumn('workflow_etapes', 'condition_json')) {
                $table->json('condition_json')->nullable()->after('type_approbation');
            }
            if (! Schema::hasColumn('workflow_etapes', 'groupe_parallele')) {
                $table->string('groupe_parallele', 50)->nullable()->after('condition_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_etapes', function (Blueprint $table) {
            $table->dropColumn(['type_approbation', 'condition_json', 'groupe_parallele']);
        });

        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_version_id');
            $table->dropColumn('objet_version');
        });

        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_conditions');
        Schema::dropIfExists('workflow_versions');

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['version', 'published_at']);
        });
    }
};
