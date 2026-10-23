<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase R6 — relances de recouvrement adossées aux appels de fonds.
 * Historique des relances (manuelles ou automatiques) avec niveau d'escalade.
 * Migration additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relances_recouvrements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appel_fonds_id')->constrained('appels_fonds')->cascadeOnDelete();
            $table->unsignedTinyInteger('niveau')->default(1);
            $table->date('date_relance');
            $table->string('canal', 30)->default('courrier');
            $table->string('destinataire')->nullable();
            $table->string('objet')->nullable();
            $table->decimal('montant_du', 18, 2)->default(0);
            $table->boolean('automatique')->default(false);
            $table->text('observations')->nullable();
            $table->foreignId('emise_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('appel_fonds_id');
            $table->index('date_relance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relances_recouvrements');
    }
};
