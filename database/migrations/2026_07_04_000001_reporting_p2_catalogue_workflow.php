<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P2 Reporting — catalogue modèles, workflow MOA, lien GED. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rapport_categories')) {
            Schema::create('rapport_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('libelle');
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->boolean('actif')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rapport_modeles')) {
            Schema::create('rapport_modeles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('categorie_id')->constrained('rapport_categories')->cascadeOnDelete();
                $table->string('type', 50)->unique();
                $table->string('titre');
                $table->text('description')->nullable();
                $table->json('parametres_schema')->nullable();
                $table->boolean('actif')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('rapports', function (Blueprint $table) {
            if (! Schema::hasColumn('rapports', 'statut_workflow')) {
                $table->string('statut_workflow', 20)->default('genere')->after('statut');
            }
            if (! Schema::hasColumn('rapports', 'valide_par')) {
                $table->foreignId('valide_par')->nullable()->after('genere_par')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('rapports', 'valide_le')) {
                $table->timestamp('valide_le')->nullable()->after('valide_par');
            }
            if (! Schema::hasColumn('rapports', 'publie_par')) {
                $table->foreignId('publie_par')->nullable()->after('signe_le')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('rapports', 'publie_le')) {
                $table->timestamp('publie_le')->nullable()->after('publie_par');
            }
            if (! Schema::hasColumn('rapports', 'modele_id')) {
                $table->foreignId('modele_id')->nullable()->after('type')->constrained('rapport_modeles')->nullOnDelete();
            }
            if (! Schema::hasColumn('rapports', 'ged_indexe')) {
                $table->boolean('ged_indexe')->default(false)->after('verrouille');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rapports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modele_id');
            $table->dropConstrainedForeignId('valide_par');
            $table->dropConstrainedForeignId('publie_par');
            $table->dropColumn(['statut_workflow', 'valide_le', 'publie_le', 'ged_indexe']);
        });
        Schema::dropIfExists('rapport_modeles');
        Schema::dropIfExists('rapport_categories');
    }
};
