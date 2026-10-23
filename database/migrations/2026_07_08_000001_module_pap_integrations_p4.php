<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P4 — alignement granulaire programmation ↔ activités PAP. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pap_activite_programmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programmation_annuelle_id')->constrained('programmations_annuelles')->cascadeOnDelete();
            $table->foreignId('activite_id')->constrained('activites')->cascadeOnDelete();
            $table->string('mode', 20)->default('auto')->comment('auto|manuel');
            $table->timestamps();

            $table->unique(['programmation_annuelle_id', 'activite_id'], 'pap_act_prog_unique');
            $table->index('activite_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pap_activite_programmations');
    }
};
