<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intégration automatique des pièces jointes à la GED : lien entre la pièce d'un dossier et son document GED.
 * ADDITIVE : colonne nullable, aucune donnée modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pieces_justificatives', function (Blueprint $table) {
            $table->foreignId('ged_document_id')->nullable()->after('televerse_par')
                ->constrained('ged_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pieces_justificatives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ged_document_id');
        });
    }
};
