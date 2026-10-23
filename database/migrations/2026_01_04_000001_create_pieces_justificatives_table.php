<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** GED (D8) : pièces justificatives rattachées polymorphiquement à tout objet. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces_justificatives', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable'); // attachable_type + attachable_id
            $table->string('nom');
            $table->string('chemin');         // chemin de stockage (disk local)
            $table->string('type_mime', 100)->nullable();
            $table->unsignedBigInteger('taille')->default(0);
            $table->string('categorie', 50)->nullable(); // facture, contrat, pv, ...
            $table->foreignId('televerse_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces_justificatives');
    }
};
