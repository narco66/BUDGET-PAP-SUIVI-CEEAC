<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase D3 — rapprochement bancaire, audit dépense, historique, journaux consolidés. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapprochements_bancaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->string('reference', 60)->unique();
            $table->string('intitule');
            $table->string('compte_bancaire', 50)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->decimal('montant_releve', 18, 2)->default(0);
            $table->decimal('montant_rapproche', 18, 2)->default(0);
            $table->enum('statut', ['brouillon', 'en_cours', 'clos', 'annule'])->default('brouillon');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cloture_le')->nullable();
            $table->timestamps();
            $table->index(['exercice_id', 'statut']);
        });

        Schema::create('rapprochement_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapprochement_bancaire_id')->constrained('rapprochements_bancaires')->cascadeOnDelete();
            $table->date('date_operation');
            $table->string('libelle');
            $table->string('reference_bancaire', 100)->nullable();
            $table->decimal('montant', 18, 2);
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->nullOnDelete();
            $table->enum('statut', ['non_rapproche', 'rapproche', 'ecart'])->default('non_rapproche');
            $table->timestamps();
            $table->index(['rapprochement_bancaire_id', 'statut']);
        });

        Schema::create('paiement_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements')->cascadeOnDelete();
            $table->foreignId('ligne_budgetaire_id')->nullable()->constrained('lignes_budgetaires')->nullOnDelete();
            $table->string('beneficiaire')->nullable();
            $table->string('reference', 100)->nullable();
            $table->decimal('montant', 18, 2);
            $table->timestamps();
            $table->index('paiement_id');
        });

        Schema::create('paiement_rejets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements')->cascadeOnDelete();
            $table->string('motif');
            $table->text('observation')->nullable();
            $table->foreignId('rejete_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejete_le');
            $table->timestamps();
        });

        Schema::create('historique_depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
            $table->string('entite_type', 80);
            $table->unsignedBigInteger('entite_id');
            $table->string('evenement', 60);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at');
            $table->index(['engagement_id', 'created_at']);
            $table->index(['entite_type', 'entite_id']);
        });

        Schema::create('audit_depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
            $table->string('reference', 60);
            $table->unsignedTinyInteger('score_conformite')->default(0);
            $table->json('snapshot');
            $table->json('anomalies')->nullable();
            $table->foreignId('audite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('audite_le');
            $table->timestamps();
            $table->index(['exercice_id', 'audite_le']);
        });

        Schema::table('paiements', function (Blueprint $table) {
            $table->foreignId('rapprochement_bancaire_id')->nullable()->after('ordre_paiement_id')
                ->constrained('rapprochements_bancaires')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rapprochement_bancaire_id');
        });

        Schema::dropIfExists('audit_depenses');
        Schema::dropIfExists('historique_depenses');
        Schema::dropIfExists('paiement_rejets');
        Schema::dropIfExists('paiement_details');
        Schema::dropIfExists('rapprochement_lignes');
        Schema::dropIfExists('rapprochements_bancaires');
    }
};
