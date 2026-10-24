<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            if (! Schema::hasColumn('engagements', 'diagnostic_credits')) {
                $table->json('diagnostic_credits')->nullable();
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE engagements MODIFY statut ENUM('brouillon','soumis','vise','rejete','annule','en_attente_arbitrage') NOT NULL DEFAULT 'brouillon'");
        }
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            if (Schema::hasColumn('engagements', 'diagnostic_credits')) {
                $table->dropColumn('diagnostic_credits');
            }
        });
    }
};
