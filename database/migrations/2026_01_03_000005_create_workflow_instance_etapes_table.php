<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Historique d'exécution de chaque étape d'une instance (qui, quand, décision). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instance_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->foreignId('workflow_etape_id')->constrained('workflow_etapes')->cascadeOnDelete();
            $table->unsignedInteger('ordre');
            $table->string('role', 50);
            $table->enum('statut', ['en_attente', 'valide', 'rejete', 'renvoye'])->default('en_attente');
            $table->foreignId('acteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_le')->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instance_etapes');
    }
};
