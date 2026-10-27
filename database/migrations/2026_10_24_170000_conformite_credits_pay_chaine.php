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

        $this->elargirStatutEngagement([
            'brouillon', 'soumis', 'vise', 'rejete', 'annule', 'en_attente_arbitrage',
        ]);
    }

    /** @param list<string> $valeurs */
    private function elargirStatutEngagement(array $valeurs): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $liste = implode(',', array_map(fn (string $v) => "'{$v}'", $valeurs));

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE engagements MODIFY statut ENUM({$liste}) NOT NULL DEFAULT 'brouillon'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE engagements DROP CONSTRAINT IF EXISTS engagements_statut_check');
            DB::statement("ALTER TABLE engagements ADD CONSTRAINT engagements_statut_check CHECK (statut::text = ANY (ARRAY[{$liste}]::text[]))");
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
