<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase P4 — Liaison programmation annuelle ↔ PAP par exercice. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmations_annuelles', function (Blueprint $table) {
            $table->foreignId('pap_id')->nullable()->after('exercice_id')->constrained('paps')->nullOnDelete();
            $table->index(['exercice_id', 'pap_id'], 'prog_ann_exercice_pap_idx');
        });
    }

    public function down(): void
    {
        Schema::table('programmations_annuelles', function (Blueprint $table) {
            $table->dropIndex('prog_ann_exercice_pap_idx');
            $table->dropConstrainedForeignId('pap_id');
        });
    }
};
