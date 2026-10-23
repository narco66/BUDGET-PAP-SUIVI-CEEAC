<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase R7 — plans d'apurement des arriérés : échéancier de règlement adossé à un appel de fonds.
 * Migration additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_apurement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appel_fonds_id')->constrained('appels_fonds')->cascadeOnDelete();
            $table->string('reference', 60)->unique();
            $table->decimal('montant_total', 18, 2)->default(0);
            $table->unsignedSmallInteger('nombre_echeances')->default(1);
            $table->date('date_debut');
            $table->string('statut', 20)->default('actif'); // actif / solde / annule
            $table->text('observations')->nullable();
            $table->foreignId('etabli_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['appel_fonds_id', 'statut']);
        });

        Schema::create('echeances_apurement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_apurement_id')->constrained('plans_apurement')->cascadeOnDelete();
            $table->unsignedSmallInteger('rang');
            $table->date('date_echeance');
            $table->decimal('montant_prevu', 18, 2)->default(0);
            $table->decimal('montant_regle', 18, 2)->default(0);
            $table->string('statut', 20)->default('a_venir'); // a_venir / partielle / honoree
            $table->date('date_reglement')->nullable();
            $table->timestamps();

            $table->unique(['plan_apurement_id', 'rang']);
            $table->index('date_echeance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('echeances_apurement');
        Schema::dropIfExists('plans_apurement');
    }
};
