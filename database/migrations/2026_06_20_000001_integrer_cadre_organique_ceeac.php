<?php

use App\Services\Institution\CadreOrganiqueCeeacService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CadreOrganiqueCeeacService::class)->synchroniser();
    }

    public function down(): void
    {
        // Migration volontairement non destructive: le cadre organique conserve les donnees liees.
    }
};
