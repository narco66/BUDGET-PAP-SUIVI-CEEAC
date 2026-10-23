<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Référentiel PTF / bailleurs (Phase B). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partenaires_financiers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle');
            $table->string('sigle', 30)->nullable();
            $table->enum('type', ['multilateral', 'bilateral', 'onu', 'technique', 'autre'])->default('multilateral');
            $table->string('pays', 80)->nullable();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partenaires_financiers');
    }
};
