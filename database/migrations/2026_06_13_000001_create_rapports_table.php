<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des rapports générés (historisation, versioning, traçabilité, signature, QR de vérification).
 * Non destructif : création d'une nouvelle table uniquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rapports')) {
            return;
        }

        Schema::create('rapports', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();          // RPT-2026-000001
            $table->string('type', 50);                          // pap, execution_axe, bailleur, categorie, rbm_gar
            $table->string('titre');
            $table->foreignId('exercice_id')->nullable()->constrained('exercices')->nullOnDelete();
            $table->string('periode', 20)->nullable();           // 2026, 2026-T1...
            $table->string('format', 10);                        // pdf, excel, word
            $table->json('parametres')->nullable();              // filtres appliqués
            $table->string('chemin')->nullable();                // chemin relatif disque
            $table->unsignedBigInteger('taille')->nullable();
            $table->char('hash', 64)->nullable();                // SHA-256 du fichier
            $table->text('signature')->nullable();               // HMAC (signature électronique)
            $table->string('qr_token', 64)->unique();            // jeton de vérification publique
            $table->unsignedInteger('version')->default(1);
            $table->string('statut', 20)->default('genere');     // genere, signe, archive
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signe_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signe_le')->nullable();
            $table->timestamps();

            $table->index(['type', 'exercice_id']);
            $table->index(['type', 'periode', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports');
    }
};
