<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P0 Suivi Indicateurs — historisation des mesures IOV (sans perte à la resaisie). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historique_mesures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesure_id')->constrained('mesures')->cascadeOnDelete();
            $table->foreignId('indicateur_id')->constrained('indicateurs')->cascadeOnDelete();
            $table->string('periode', 20);
            $table->string('action', 40);
            $table->decimal('valeur_avant', 18, 4)->nullable();
            $table->decimal('valeur_apres', 18, 4)->nullable();
            $table->string('statut_avant', 20)->nullable();
            $table->string('statut_apres', 20)->nullable();
            $table->decimal('taux_avant', 7, 2)->nullable();
            $table->decimal('taux_apres', 7, 2)->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['indicateur_id', 'periode'], 'hist_mes_ind_per_idx');
            $table->index(['mesure_id', 'created_at'], 'hist_mes_mes_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_mesures');
    }
};
