<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Piste d'audit inaltérable (D11 / ENF-SEC-04).
 * Journal polymorphe : chaque action sensible y est tracée (auteur, contexte, avant/après).
 * Pas de updated_at : les écritures ne sont jamais modifiées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journaux_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_nom')->nullable();   // snapshot du nom (survit à la suppression du compte)
            $table->nullableMorphs('auditable');       // auditable_type + auditable_id
            $table->string('action', 30);              // created | updated | deleted | login | logout | valide | rejete ...
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journaux_audit');
    }
};
