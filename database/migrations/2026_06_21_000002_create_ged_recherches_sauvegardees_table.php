<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 3 GED : recherches avancées sauvegardées par utilisateur. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ged_recherches_sauvegardees')) {
            Schema::create('ged_recherches_sauvegardees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
                $table->string('nom', 120);
                $table->json('filtres');
                $table->boolean('par_defaut')->default(false);
                $table->timestamps();
                $table->unique(['utilisateur_id', 'nom']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ged_recherches_sauvegardees');
    }
};
