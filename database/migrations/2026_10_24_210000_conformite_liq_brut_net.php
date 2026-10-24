<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            if (! Schema::hasColumn('liquidations', 'montant_brut')) {
                $table->decimal('montant_brut', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('liquidations', 'montant_retenues')) {
                $table->decimal('montant_retenues', 18, 2)->nullable();
            }
            if (! Schema::hasColumn('liquidations', 'montant_net')) {
                $table->decimal('montant_net', 18, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            foreach (['montant_brut', 'montant_retenues', 'montant_net'] as $col) {
                if (Schema::hasColumn('liquidations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
