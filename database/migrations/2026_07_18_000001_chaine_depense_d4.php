<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase D4 — multi-devises paiements et écritures comptables SYSCOHADA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('devise', 3)->default('XAF')->after('montant');
            $table->decimal('taux_change', 12, 6)->default(1)->after('devise');
            $table->decimal('montant_xaf', 18, 2)->nullable()->after('taux_change');
        });

        Schema::create('ecritures_comptables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->nullOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
            $table->string('journal', 10)->default('OD');
            $table->string('numero_piece', 40);
            $table->date('date_ecriture');
            $table->string('compte_debit', 20);
            $table->string('compte_credit', 20);
            $table->decimal('montant', 18, 2);
            $table->string('devise', 3)->default('XAF');
            $table->string('libelle');
            $table->string('reference', 60)->nullable();
            $table->enum('statut', ['brouillon', 'valide', 'exportee'])->default('valide');
            $table->timestamp('exportee_le')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['exercice_id', 'date_ecriture']);
            $table->index(['paiement_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecritures_comptables');

        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn(['devise', 'taux_change', 'montant_xaf']);
        });
    }
};
