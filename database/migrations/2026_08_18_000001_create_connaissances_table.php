<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connaissances', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('slug')->unique();
            $table->string('titre');
            $table->string('type', 20)->index(); // wiki | procedure
            $table->string('module', 40)->index();
            $table->string('categorie', 80)->nullable();
            $table->text('resume')->nullable();
            $table->longText('contenu');
            $table->string('statut', 20)->default('brouillon')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('auteur_id')->constrained('users');
            $table->foreignId('publie_par_id')->nullable()->constrained('users');
            $table->timestamp('publie_le')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('ged_document_id')->nullable()->constrained('ged_documents')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('connaissance_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connaissance_id')->constrained('connaissances')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('contenu');
            $table->foreignId('auteur_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['connaissance_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connaissance_versions');
        Schema::dropIfExists('connaissances');
    }
};
