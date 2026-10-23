<?php

use App\Services\Institution\CadreOrganiqueCeeacService;
use Illuminate\Database\Migrations\Migration;

/** Phase A1 — enrichissement cadre organique CEEAC (départements métier + directions). */
return new class extends Migration
{
    public function up(): void
    {
        app(CadreOrganiqueCeeacService::class)->synchroniser();
    }

    public function down(): void
    {
        // Migration additive : pas de rollback destructif.
    }
};
