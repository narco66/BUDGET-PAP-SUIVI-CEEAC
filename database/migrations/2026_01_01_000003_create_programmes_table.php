<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('intitule');
            $table->foreignId('structure_id')->constrained('structures')->cascadeOnUpdate()->restrictOnDelete();
            // pilier_id : FK ajoutée dans la cross migration (piliers créés ensuite)
            $table->unsignedBigInteger('pilier_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
