<?php

namespace Database\Seeders\Demo;

use App\Models\Fonction;
use App\Models\Structure;
use Illuminate\Database\Seeder;

/** Enrichit l'organigramme de demonstration sans detruire les donnees existantes. */
class InstitutionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $dpres = Structure::where('code', 'DPRES')->value('id') ?: Structure::where('code', 'PRES')->value('id');
        $dsg = Structure::where('code', 'DSG')->value('id') ?: Structure::where('code', 'SG')->value('id');
        $dati = Structure::where('code', 'DATI')->value('id') ?: $dpres;

        Structure::updateOrCreate(
            ['code' => 'COM-INT'],
            [
                'libelle' => 'Commissariat a l Integration Physique, Economique et Monetaire',
                'sigle' => 'CIPEM',
                'type' => 'commissariat',
                'parent_id' => $dpres,
                'niveau' => 2,
                'ordre' => 2,
                'actif' => true,
            ]
        );

        $drh = Structure::updateOrCreate(
            ['code' => 'DIR-RH'],
            [
                'libelle' => 'Direction des Ressources Humaines',
                'sigle' => 'DRH',
                'type' => 'direction',
                'parent_id' => $dsg,
                'niveau' => 3,
                'ordre' => 920,
                'actif' => true,
            ]
        );

        Structure::updateOrCreate(
            ['code' => 'SVC-PERS'],
            [
                'libelle' => 'Service du Personnel',
                'sigle' => 'SP',
                'type' => 'service',
                'parent_id' => $drh->id,
                'niveau' => 4,
                'ordre' => 1,
                'actif' => true,
            ]
        );

        Structure::updateOrCreate(
            ['code' => 'DIR-INFRA'],
            [
                'libelle' => 'Direction des Infrastructures',
                'sigle' => 'DI',
                'type' => 'direction',
                'parent_id' => $dati,
                'niveau' => 2,
                'ordre' => 20,
                'actif' => true,
            ]
        );

        foreach ([
            ['F-ORD', 'Ordonnateur'],
            ['F-CF', 'Controleur financier'],
            ['F-CPT', 'Comptable'],
            ['F-DIR', 'Directeur'],
            ['F-CS', 'Chef de service'],
        ] as [$code, $libelle]) {
            Fonction::updateOrCreate(['code' => $code], ['libelle' => $libelle, 'actif' => true]);
        }
    }
}
