<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Périmètre d'accès aux données par utilisateur.
 * type : global | departement | direction | service | programme | exercice
 *        | lecture_seule | propres_dossiers | a_valider
 * scope_id : identifiant de l'objet ciblé (structure/programme/exercice) selon le type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_access_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', [
                'global', 'departement', 'direction', 'service', 'programme', 'exercice',
                'lecture_seule', 'propres_dossiers', 'a_valider',
            ]);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_access_scopes');
    }
};
