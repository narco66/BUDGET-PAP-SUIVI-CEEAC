<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Liaison explicite PAP ↔ piliers RBM + statut en_revision pour révisions post-publication. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('piliers', 'pap_id')) {
            Schema::table('piliers', function (Blueprint $table) {
                $table->foreignId('pap_id')->nullable()->after('exercice_id')->constrained('paps')->nullOnDelete();
            });
        }

        DB::table('piliers')->whereNull('pap_id')->orderBy('id')->chunkById(200, function ($piliers) {
            foreach ($piliers as $pilier) {
                $papId = DB::table('paps')->where('exercice_id', $pilier->exercice_id)->value('id');
                if ($papId) {
                    DB::table('piliers')->where('id', $pilier->id)->update(['pap_id' => $papId]);
                }
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE paps MODIFY COLUMN statut ENUM(
                    'brouillon','en_validation','valide','publie','rejete','clos','en_revision'
                ) NOT NULL DEFAULT 'brouillon'"
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('piliers', 'pap_id')) {
            Schema::table('piliers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pap_id');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE paps MODIFY COLUMN statut ENUM(
                    'brouillon','en_validation','valide','publie','rejete','clos'
                ) NOT NULL DEFAULT 'brouillon'"
            );
        }
    }
};
