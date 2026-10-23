<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('axes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilier_id')->constrained('piliers')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('intitule');
            $table->text('objectif_strategique')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['pilier_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('axes');
    }
};
