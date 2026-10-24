<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiement_rejets', function (Blueprint $table) {
            if (! Schema::hasColumn('paiement_rejets', 'type')) {
                $table->string('type', 30)->default('workflow');
            }
            if (! Schema::hasColumn('paiement_rejets', 'preuve')) {
                $table->string('preuve', 500)->nullable();
            }
            if (! Schema::hasColumn('paiement_rejets', 'contrepassation_appliquee')) {
                $table->boolean('contrepassation_appliquee')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiement_rejets', function (Blueprint $table) {
            foreach (['type', 'preuve', 'contrepassation_appliquee'] as $col) {
                if (Schema::hasColumn('paiement_rejets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
