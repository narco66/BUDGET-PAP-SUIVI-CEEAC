<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ordonnancement (D5 — EF-D5-05 : ordre de payer). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordonnancements', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('liquidation_id')->constrained('liquidations')->restrictOnDelete();
            $table->decimal('montant', 18, 2)->default(0);
            $table->date('date_ordonnancement')->nullable();
            $table->enum('statut', ['brouillon', 'soumis', 'valide', 'rejete'])->default('brouillon');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->boolean('compteurs_appliques')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordonnancements');
    }
};
