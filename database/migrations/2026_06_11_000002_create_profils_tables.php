<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Profils fonctionnels (FK vers roles → daté après la migration Spatie). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profils', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle');
            $table->enum('type', [
                'institutionnel', 'metier', 'budgetaire', 'pap_gar', 'validation', 'consultation', 'audit',
            ])->default('metier');
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('profil_role', function (Blueprint $table) {
            $table->foreignId('profil_id')->constrained('profils')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['profil_id', 'role_id']);
        });

        Schema::create('user_profils', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('profil_id')->constrained('profils')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'profil_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profils');
        Schema::dropIfExists('profil_role');
        Schema::dropIfExists('profils');
    }
};
