<?php

namespace Database\Seeders;

use App\Models\Exercice;
use App\Models\Structure;
use App\Models\User;
use App\Services\Pap\PapCompletenessService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Phase P0 — complète les données PAP après Pap2026Seeder et Budget2026Seeder. */
class PapP0Seeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            throw new RuntimeException('Exercice 2026 absent.');
        }

        $responsable = User::firstWhere('email', 'directeur.plan@ceeac.test')
            ?? $this->creerDirecteurDemo();

        $stats = app(PapCompletenessService::class)->completer($exercice->id, [
            'responsable_id' => $responsable->id,
        ]);

        if (! ($stats['succes'] ?? false) && ($stats['anomalies_critiques'] ?? 0) > 0) {
            throw new RuntimeException('Complétion P0 échouée : anomalies critiques PAP.');
        }
    }

    private function creerDirecteurDemo(): User
    {
        $structure = Structure::firstWhere('code', 'DEPT-PLAN');

        $user = User::updateOrCreate(
            ['email' => 'directeur.plan@ceeac.test'],
            [
                'name' => 'Directeur Planification (démo)',
                'matricule' => 'DIR-PLAN-001',
                'password' => Hash::make('password'),
                'structure_id' => $structure?->id,
                'actif' => true,
            ]
        );
        $user->syncRoles(['directeur']);

        return $user;
    }
}
