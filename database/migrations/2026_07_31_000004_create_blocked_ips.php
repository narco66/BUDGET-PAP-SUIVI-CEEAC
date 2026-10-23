<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Administration Technique — Lot T4 (durcissement sécurité).
 * `blocked_ips` : registre de verrouillage anti brute-force (auto après N échecs, ou manuel/permanent).
 * Les tentatives elles-mêmes sont déjà tracées dans `login_logs` (équivaut à login_attempts).
 * NON DESTRUCTIF (création conditionnelle).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blocked_ips')) {
            Schema::create('blocked_ips', function (Blueprint $t) {
                $t->id();
                $t->string('ip', 45)->index();
                $t->string('motif')->nullable();
                $t->unsignedInteger('tentatives')->default(0);
                $t->timestamp('bloque_jusqua')->nullable();
                $t->boolean('permanent')->default(false);
                $t->foreignId('bloque_par')->nullable()->constrained('users')->nullOnDelete(); // blocage manuel
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};
