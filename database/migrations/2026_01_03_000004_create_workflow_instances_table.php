<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Instance d'un circuit attachée polymorphiquement à un objet métier. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->restrictOnDelete();
            $table->morphs('objet'); // objet_type + objet_id (Pap, Budget, Engagement...)
            $table->enum('statut', ['en_cours', 'valide', 'rejete'])->default('en_cours');
            $table->unsignedInteger('etape_courante')->default(1);
            $table->foreignId('demarre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
