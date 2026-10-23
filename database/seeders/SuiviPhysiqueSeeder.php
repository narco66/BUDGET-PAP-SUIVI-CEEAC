<?php

namespace Database\Seeders;

use Database\Seeders\Demo\SuiviDemoSeeder;
use Illuminate\Database\Seeder;

/** Phase S4 — seeder suivi physique (délègue à SuiviDemoSeeder). */
class SuiviPhysiqueSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SuiviPermissionsSeeder::class);
        $this->call(SuiviDemoSeeder::class);
    }
}
