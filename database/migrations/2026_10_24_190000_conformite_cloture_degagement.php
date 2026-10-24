<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            if (! Schema::hasColumn('engagements', 'chaine_cloturee_le')) {
                $table->timestamp('chaine_cloturee_le')->nullable();
            }
        });

        Schema::table('paiements', function (Blueprint $table) {
            if (! Schema::hasColumn('paiements', 'motif_partiel')) {
                $table->string('motif_partiel', 500)->nullable();
            }
            if (! Schema::hasColumn('paiements', 'rib')) {
                $table->string('rib', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            if (Schema::hasColumn('engagements', 'chaine_cloturee_le')) {
                $table->dropColumn('chaine_cloturee_le');
            }
        });
        Schema::table('paiements', function (Blueprint $table) {
            foreach (['motif_partiel', 'rib'] as $col) {
                if (Schema::hasColumn('paiements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
