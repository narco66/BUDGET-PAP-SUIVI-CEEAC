<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Source de financement : interne CEEAC (CCI) ou externe PTF. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources_financement', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle');
            $table->enum('type', ['interne_ceeac', 'externe_ptf']);
            $table->string('bailleur')->nullable(); // ex: BAD, UE, Banque Mondiale...
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources_financement');
    }
};
