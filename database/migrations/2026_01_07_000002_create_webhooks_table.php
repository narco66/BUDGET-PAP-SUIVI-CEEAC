<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Abonnements webhooks (D12 — EF-D12-03) + traçabilité des livraisons. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('url', 500);
            $table->string('evenement', 60); // ex: engagement.vise, paiement.valide
            $table->string('secret', 80)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['evenement', 'actif']);
        });

        Schema::create('webhook_livraisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('webhooks')->cascadeOnDelete();
            $table->string('evenement', 60);
            $table->json('payload')->nullable();
            $table->unsignedSmallInteger('statut_http')->nullable();
            $table->boolean('succes')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_livraisons');
        Schema::dropIfExists('webhooks');
    }
};
