<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Import : lots, modèles de correspondance, lignes de préparation (staging), anomalies et journal.
 * Additive : aucune table existante n'est modifiée. Les approbations passent par le moteur de workflow
 * existant (workflow_instances) — pas de table d'approbation redondante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_lots', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('domaine', 40);
            $table->string('mode', 30)->default('insertion');
            $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
            $table->string('statut', 30)->default('brouillon');
            // Fichier source (stockage privé) et empreinte SHA-256 : identité et intégrité du fichier.
            $table->string('fichier_nom')->nullable();
            $table->string('fichier_chemin')->nullable();
            $table->string('fichier_empreinte', 64)->nullable();
            $table->unsignedBigInteger('fichier_taille')->nullable();
            $table->string('fichier_type', 120)->nullable();
            $table->string('feuille')->nullable();
            $table->json('entetes')->nullable();
            $table->json('mapping')->nullable();
            $table->json('totaux')->nullable();
            $table->unsignedInteger('progression')->default(0);
            $table->text('motif')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('analyse_le')->nullable();
            $table->timestamp('soumis_le')->nullable();
            $table->foreignId('approuve_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approuve_le')->nullable();
            $table->foreignId('execute_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('execute_le')->nullable();
            $table->timestamp('termine_le')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['domaine', 'statut']);
            $table->index('fichier_empreinte');
            $table->index(['cree_par', 'created_at']);
        });

        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('domaine', 40);
            $table->string('nom', 120);
            $table->json('colonnes');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['domaine', 'nom']);
        });

        Schema::create('import_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_lot_id')->constrained('import_lots')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->json('donnees_brutes');
            $table->json('donnees')->nullable();
            $table->string('cle_metier')->nullable();
            // inserer | mettre_a_jour | identique | ignorer | conflit | erreur
            $table->string('operation', 20);
            $table->string('cible_type')->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->json('differences')->nullable();
            // en_attente | execute | echec
            $table->string('statut', 20)->default('en_attente');
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['import_lot_id', 'operation']);
            $table->index(['import_lot_id', 'cle_metier']);
            $table->index(['import_lot_id', 'numero']);
        });

        Schema::create('import_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_lot_id')->constrained('import_lots')->cascadeOnDelete();
            $table->foreignId('import_ligne_id')->nullable()->constrained('import_lignes')->cascadeOnDelete();
            $table->unsignedInteger('numero_ligne')->nullable();
            $table->string('colonne', 120)->nullable();
            $table->text('valeur')->nullable();
            $table->string('regle', 60);
            // bloquante | majeure | avertissement
            $table->string('gravite', 20);
            $table->text('message');
            $table->text('suggestion')->nullable();
            $table->timestamps();

            $table->index(['import_lot_id', 'gravite']);
        });

        Schema::create('import_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_lot_id')->constrained('import_lots')->cascadeOnDelete();
            $table->string('type', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut_avant', 30)->nullable();
            $table->string('statut_apres', 30)->nullable();
            $table->text('commentaire')->nullable();
            $table->json('donnees')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['import_lot_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_evenements');
        Schema::dropIfExists('import_anomalies');
        Schema::dropIfExists('import_lignes');
        Schema::dropIfExists('import_mappings');
        Schema::dropIfExists('import_lots');
    }
};
