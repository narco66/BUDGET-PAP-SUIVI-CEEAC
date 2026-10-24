<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Conformité EB (procédures 24/10/2026) — additive, non destructive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expressions_besoin', function (Blueprint $table) {
            if (! Schema::hasColumn('expressions_besoin', 'categorie')) {
                $table->string('categorie', 20)->default('pap')->after('numero');
            }
            if (! Schema::hasColumn('expressions_besoin', 'heritage_pap')) {
                $table->json('heritage_pap')->nullable();
            }
            if (! Schema::hasColumn('expressions_besoin', 'alerte_credits')) {
                $table->json('alerte_credits')->nullable();
            }
        });

        if (! Schema::hasTable('expression_besoin_lignes')) {
            Schema::create('expression_besoin_lignes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expression_besoin_id')->constrained('expressions_besoin')->cascadeOnDelete();
                $table->unsignedInteger('ordre')->default(1);
                $table->string('libelle');
                $table->decimal('quantite', 18, 4)->default(1);
                $table->string('unite', 30)->nullable();
                $table->decimal('prix_unitaire', 18, 2)->default(0);
                $table->decimal('taxe', 18, 2)->default(0);
                $table->decimal('montant', 18, 2)->default(0);
                $table->timestamps();
                $table->index(['expression_besoin_id', 'ordre']);
            });
        }

        if (! Schema::hasTable('chaine_depense_parametres')) {
            Schema::create('chaine_depense_parametres', function (Blueprint $table) {
                $table->id();
                $table->string('cle', 80)->unique();
                $table->text('valeur');
                $table->unsignedInteger('version')->default(1);
                $table->boolean('juridiquement_valide')->default(false);
                $table->string('commentaire')->nullable();
                $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (DB::table('chaine_depense_parametres')->where('cle', 'ordonnancement.seuil_xaf')->doesntExist()) {
            DB::table('chaine_depense_parametres')->insert([
                'cle' => 'ordonnancement.seuil_xaf',
                'valeur' => config('chaine_depense.ordonnancement.seuil_xaf', '5000000.00'),
                'version' => 1,
                'juridiquement_valide' => false,
                'commentaire' => 'Hypothèse opérationnelle du dossier ORD v1.0 — assiette net à valider juridiquement.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('chaine_depense_parametres')->where('cle', 'ordonnancement.assiette')->doesntExist()) {
            DB::table('chaine_depense_parametres')->insert([
                'cle' => 'ordonnancement.assiette',
                'valeur' => 'net',
                'version' => 1,
                'juridiquement_valide' => false,
                'commentaire' => 'Convention documentaire : montant net à payer. Non opposable sans validation officielle.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $doublons = DB::table('engagements')
            ->select('expression_besoin_id')
            ->whereNotNull('expression_besoin_id')
            ->whereNull('deleted_at')
            ->groupBy('expression_besoin_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $doublons && Schema::hasTable('engagements')) {
            try {
                Schema::table('engagements', function (Blueprint $table) {
                    $table->unique('expression_besoin_id', 'engagements_expression_besoin_id_unique');
                });
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            try {
                $table->dropUnique('engagements_expression_besoin_id_unique');
            } catch (\Throwable) {
            }
        });
        Schema::dropIfExists('expression_besoin_lignes');
        Schema::table('expressions_besoin', function (Blueprint $table) {
            foreach (['categorie', 'heritage_pap', 'alerte_credits'] as $col) {
                if (Schema::hasColumn('expressions_besoin', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
