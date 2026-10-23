<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel institutionnel (D1) — hiérarchie auto-référencée.
 * Présidence / Vice-Présidence / SG / Départements / Directions / Services.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structures', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle');
            $table->string('sigle', 30)->nullable();
            $table->enum('type', [
                'presidence', 'vice_presidence', 'secretariat_general',
                'commissariat', 'departement', 'direction', 'service', 'cellule',
            ]);
            $table->foreignId('parent_id')->nullable()
                ->constrained('structures')->nullOnDelete();
            $table->unsignedTinyInteger('niveau')->default(0);
            $table->unsignedInteger('ordre')->default(0);
            // responsable_user_id : FK ajoutée après altération de users (voir cross migration)
            $table->unsignedBigInteger('responsable_user_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'actif']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structures');
    }
};
