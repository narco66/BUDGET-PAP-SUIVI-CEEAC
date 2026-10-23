<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 11 États membres de la CEEAC (clé de répartition CCI). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etats_membres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();   // ex: 721010 (rubrique CCI)
            $table->string('pays');
            $table->decimal('cle_repartition', 8, 4)->nullable(); // % CCI
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etats_membres');
    }
};
