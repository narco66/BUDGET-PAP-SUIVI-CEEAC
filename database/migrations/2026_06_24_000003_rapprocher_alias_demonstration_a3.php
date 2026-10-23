<?php

use App\Services\Institution\AliasDemonstrationService;
use Illuminate\Database\Migrations\Migration;

/** Phase A3 — rapprochement alias démo vers structures officielles. */
return new class extends Migration
{
    public function up(): void
    {
        app(AliasDemonstrationService::class)->rapprocher();
    }

    public function down(): void
    {
        // Migration additive : pas de rollback destructif.
    }
};
