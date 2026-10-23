<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extension des utilisateurs pour le module Accès.
 * `actif` (existant) reste la porte d'authentification ; `statut` ajoute les 3 états
 * (actif / suspendu / désactivé) tenus synchrones par UserService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('telephone');
            $table->enum('statut', ['actif', 'suspendu', 'desactive'])->default('actif')->after('actif');
            $table->boolean('doit_changer_mot_de_passe')->default(false)->after('statut');
            $table->foreignId('poste_id')->nullable()->after('fonction_id')->constrained('postes')->nullOnDelete();
            $table->timestamp('derniere_connexion_at')->nullable()->after('doit_changer_mot_de_passe');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('poste_id');
            $table->dropColumn(['avatar', 'statut', 'doit_changer_mot_de_passe', 'derniere_connexion_at', 'deleted_at']);
        });
    }
};
