<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (! Schema::hasColumn('paiements', 'rib_modifie_par')) {
                $table->unsignedBigInteger('rib_modifie_par')->nullable()->after('rib');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (Schema::hasColumn('paiements', 'rib_modifie_par')) {
                $table->dropColumn('rib_modifie_par');
            }
        });
    }
};
