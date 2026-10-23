<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rattachement multiple d'un utilisateur à plusieurs structures (users.structure_id = principal). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_structures', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
            $table->boolean('principal')->default(false);
            $table->timestamps();
            $table->primary(['user_id', 'structure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_structures');
    }
};
