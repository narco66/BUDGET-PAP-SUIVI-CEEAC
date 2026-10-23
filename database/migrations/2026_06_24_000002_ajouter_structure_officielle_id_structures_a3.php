<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase A3 — lien explicite alias démo → structure officielle CEEAC. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            if (! Schema::hasColumn('structures', 'structure_officielle_id')) {
                $table->foreignId('structure_officielle_id')
                    ->nullable()
                    ->after('parent_id')
                    ->constrained('structures')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            if (Schema::hasColumn('structures', 'structure_officielle_id')) {
                $table->dropConstrainedForeignId('structure_officielle_id');
            }
        });
    }
};
