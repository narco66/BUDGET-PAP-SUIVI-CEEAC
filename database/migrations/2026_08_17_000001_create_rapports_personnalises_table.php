<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P3-1 — Constructeur de rapports personnalisés. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapports_personnalises', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('source', 40);
            $table->json('colonnes');
            $table->json('filtres')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->boolean('partage')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports_personnalises');
    }
};
