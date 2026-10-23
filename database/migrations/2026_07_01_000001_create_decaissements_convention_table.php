<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase R8 — décaissements des conventions PTF, ligne à ligne, avec rattachement
 * facultatif à une activité du PAP (traçabilité du financement des activités).
 * Migration additive : ne modifie pas la table `conventions_financement`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decaissements_convention', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convention_id')->constrained('conventions_financement')->cascadeOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();
            $table->string('reference', 60)->unique();
            $table->string('objet');
            $table->decimal('montant', 18, 2)->default(0);
            $table->string('devise', 3)->default('XAF');
            $table->decimal('taux_change', 18, 6)->default(1);
            $table->decimal('montant_xaf', 18, 2)->default(0);
            $table->date('date_decaissement');
            $table->string('statut', 20)->default('execute'); // prevu / execute / annule
            $table->text('observations')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['convention_id', 'statut']);
            $table->index('activite_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decaissements_convention');
    }
};
