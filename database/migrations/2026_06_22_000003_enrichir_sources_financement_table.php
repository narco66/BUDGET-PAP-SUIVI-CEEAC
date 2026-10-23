<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Enrichissement sources de financement + lien PTF (Phase B). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources_financement', function (Blueprint $table) {
            $table->foreignId('partenaire_id')->nullable()->after('id')->constrained('partenaires_financiers')->nullOnDelete();
            $table->enum('classification', [
                'contribution_statutaire', 'contribution_volontaire', 'fonds_propres',
                'fonds_communautaires', 'subvention', 'cooperation', 'projet_finance', 'fonds_special',
            ])->nullable()->after('type');
            $table->text('description')->nullable()->after('bailleur');
        });
    }

    public function down(): void
    {
        Schema::table('sources_financement', function (Blueprint $table) {
            $table->dropForeign(['partenaire_id']);
            $table->dropColumn(['partenaire_id', 'classification', 'description']);
        });
    }
};
