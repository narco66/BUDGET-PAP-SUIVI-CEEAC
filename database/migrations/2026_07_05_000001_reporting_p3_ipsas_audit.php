<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P3 Reporting — données préparatoires IPSAS et journal audit. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rapports_ipsas')) {
            Schema::create('rapports_ipsas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rapport_id')->constrained('rapports')->cascadeOnDelete();
                $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
                $table->string('reference_note', 40)->default('IPSAS-BASE');
                $table->json('donnees_preparatoires');
                $table->timestamps();
                $table->unique('rapport_id');
            });
        }

        if (! Schema::hasTable('audit_reporting')) {
            Schema::create('audit_reporting', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rapport_id')->nullable()->constrained('rapports')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 40);
                $table->string('type_rapport', 50)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->json('metadonnees')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['action', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_reporting');
        Schema::dropIfExists('rapports_ipsas');
    }
};
