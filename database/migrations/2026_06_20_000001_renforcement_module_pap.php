<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Historisation PAP (soumissions, publications, révisions). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pap_historiques')) {
            return;
        }

        Schema::create('pap_historiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pap_id')->constrained('paps')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('action', 40);
            $table->json('snapshot')->nullable();
            $table->text('motif')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pap_historiques');
    }
};
