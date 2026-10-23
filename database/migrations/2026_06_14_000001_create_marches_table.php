<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marchés publics / Plan de Passation des Marchés (PPM). Création non destructive. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marches')) {
            return;
        }

        Schema::create('marches', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('expression_besoin_id')->nullable()->constrained('expressions_besoin')->nullOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained('engagements')->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->constrained('structures')->nullOnDelete();
            $table->string('objet');
            $table->enum('type_marche', ['fournitures', 'services', 'travaux', 'prestations_intellectuelles'])->default('fournitures');
            $table->enum('mode_passation', ['appel_offres_ouvert', 'appel_offres_restreint', 'consultation', 'gre_a_gre', 'demande_cotation'])->default('appel_offres_ouvert');
            $table->decimal('montant_estime', 18, 2)->default(0);
            $table->decimal('montant_attribue', 18, 2)->default(0);
            $table->string('attributaire')->nullable();
            $table->date('date_lancement')->nullable();
            $table->date('date_attribution')->nullable();
            $table->enum('statut', ['planifie', 'lance', 'en_evaluation', 'attribue', 'signe', 'execute', 'resilie', 'infructueux'])->default('planifie');
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exercice_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marches');
    }
};
