<?php

namespace App\Services\Budget;

use App\Imports\StructureBudgetLignesImport;
use App\Support\Montant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/** Prévisualisation et exécution d'import structure budgétaire (fichier temporaire en session). */
class StructureBudgetImportService
{
    /**
     * Analyse un fichier sans persistance.
     *
     * @return array{
     *     total:int, valides:int, rejets:array, colonnes_detectees:array, colonnes_manquantes:array, apercu:Collection
     * }
     */
    public function previsualiser(string $cheminAbsolu, int $exerciceId): array
    {
        $rows = (Excel::toCollection(new StructureBudgetLignesImport($exerciceId, dryRun: true), $cheminAbsolu)->first() ?? collect())
            ->map(fn ($r) => is_array($r) ? $r : $r->toArray());

        $colonnesDetectees = $rows->isNotEmpty() ? array_keys($rows->first()) : [];
        $colonnesManquantes = array_values(array_diff(StructureBudgetLignesImport::COLONNES_REQUISES, $colonnesDetectees));

        $valides = 0;
        $rejets = [];
        foreach ($rows as $i => $row) {
            $motif = StructureBudgetLignesImport::motifInvalidite((array) $row, $exerciceId);
            if ($motif === null) {
                $valides++;
            } elseif (count($rejets) < 50) {
                $rejets[] = ['ligne' => $i + 2, 'motif' => $motif];
            }
        }

        return [
            'total' => $rows->count(),
            'valides' => $valides,
            'rejets' => $rejets,
            'colonnes_detectees' => $colonnesDetectees,
            'colonnes_manquantes' => $colonnesManquantes,
            'apercu' => $rows->take(10),
        ];
    }

    /**
     * @param  array{total:int, valides:int, rejets:array, colonnes_detectees:array, colonnes_manquantes:array, apercu:Collection}  $apercu
     * @return array<string, mixed>
     */
    public function serialiserApercu(array $apercu, string $fichier): array
    {
        $lignes = collect($apercu['apercu'])->map(function ($row) {
            $data = is_array($row) ? $row : (array) $row;
            if (array_key_exists('montant_ouvert', $data)) {
                $data['montant_ouvert'] = Montant::api($data['montant_ouvert']);
            }

            return $data;
        })->values()->all();

        $manquantes = $apercu['colonnes_manquantes'];

        return [
            'fichier' => $fichier,
            'total' => $apercu['total'],
            'valides' => $apercu['valides'],
            'rejets' => $apercu['rejets'],
            'colonnes_detectees' => $apercu['colonnes_detectees'],
            'colonnes_manquantes' => $manquantes,
            'apercu' => $lignes,
            'peut_confirmer' => $apercu['valides'] > 0 && $manquantes === [],
        ];
    }

    public function executer(string $cheminAbsolu, int $exerciceId): StructureBudgetLignesImport
    {
        $import = new StructureBudgetLignesImport($exerciceId, dryRun: false);
        Excel::import($import, $cheminAbsolu);

        return $import;
    }

    public function supprimerFichierTemporaire(?string $cheminRelatif): void
    {
        if ($cheminRelatif && Storage::exists($cheminRelatif)) {
            Storage::delete($cheminRelatif);
        }
    }
}
