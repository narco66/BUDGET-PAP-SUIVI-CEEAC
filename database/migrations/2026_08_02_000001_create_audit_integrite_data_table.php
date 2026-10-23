<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Snapshots du contrôle d'intégrité global (P2-10). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_integrite_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
            $table->unsignedTinyInteger('score_conformite')->default(0);
            $table->unsignedInteger('anomalies_total')->default(0);
            $table->unsignedInteger('anomalies_critiques')->default(0);
            $table->json('modules');
            $table->json('snapshot');
            $table->timestamp('audite_le');
            $table->timestamps();

            $table->index(['exercice_id', 'audite_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_integrite_data');
    }
};
