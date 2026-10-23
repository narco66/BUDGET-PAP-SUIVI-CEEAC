<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P3 — risques PAP, mesures correctives, causes d'écarts, snapshots complets. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pap_risques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pap_id')->constrained('paps')->cascadeOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();
            $table->string('code', 40);
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->enum('probabilite', ['faible', 'moyenne', 'elevee'])->default('moyenne');
            $table->enum('impact', ['faible', 'moyen', 'eleve', 'critique'])->default('moyen');
            $table->enum('statut', ['ouvert', 'attenue', 'clos'])->default('ouvert');
            $table->text('mitigation')->nullable();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['pap_id', 'code']);
            $table->index(['pap_id', 'statut']);
        });

        Schema::create('pap_mesures_correctives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pap_id')->constrained('paps')->cascadeOnDelete();
            $table->foreignId('pap_risque_id')->nullable()->constrained('pap_risques')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();
            $table->string('code', 40)->nullable();
            $table->text('description');
            $table->date('echeance')->nullable();
            $table->enum('statut', ['planifiee', 'en_cours', 'realisee', 'annulee'])->default('planifiee');
            $table->text('resultat')->nullable();
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pap_id', 'statut']);
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->text('cause_ecart')->nullable()->after('taux_realisation_financier');
            $table->text('mesure_corrective')->nullable()->after('cause_ecart');
        });

        Schema::table('collectes', function (Blueprint $table) {
            $table->text('cause_ecart')->nullable()->after('ecart');
        });

        Schema::table('pap_historiques', function (Blueprint $table) {
            $table->boolean('snapshot_complet')->default(false)->after('snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('pap_historiques', function (Blueprint $table) {
            $table->dropColumn('snapshot_complet');
        });

        Schema::table('collectes', function (Blueprint $table) {
            $table->dropColumn('cause_ecart');
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->dropColumn(['cause_ecart', 'mesure_corrective']);
        });

        Schema::dropIfExists('pap_mesures_correctives');
        Schema::dropIfExists('pap_risques');
    }
};
