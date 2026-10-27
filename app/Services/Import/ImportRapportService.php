<?php

namespace App\Services\Import;

use App\Models\ImportAnomalie;
use App\Models\ImportLigne;
use App\Models\ImportLot;
use App\Services\Import\Support\Normaliseur;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapports d'un lot : anomalies (ligne, colonne, valeur, règle, gravité, message, correction suggérée) et plan
 * d'opérations, en CSV ou Excel. Les valeurs interprétables comme formules sont neutralisées (injection CSV).
 */
class ImportRapportService
{
    public function __construct(private readonly ImportDomaineRegistry $domaines) {}

    public function anomalies(ImportLot $lot, string $format): StreamedResponse
    {
        $entetes = ['Ligne', 'Colonne', 'Valeur', 'Règle', 'Gravité', 'Message', 'Correction suggérée'];
        $lignes = $lot->anomalies()->orderBy('numero_ligne')->orderBy('id')->get()->map(fn (ImportAnomalie $a) => [
            $a->numero_ligne, $a->colonne, $a->valeur, $a->regle, ImportAnomalie::GRAVITES[$a->gravite] ?? $a->gravite, $a->message, $a->suggestion,
        ]);

        return $this->telecharger("anomalies-{$lot->reference}", $entetes, $lignes->all(), $format);
    }

    public function operations(ImportLot $lot, string $format): StreamedResponse
    {
        $champs = array_map(fn ($c) => $c->champ, $this->domaines->get($lot->domaine)->champs());
        $entetes = array_merge(['Ligne', 'Opération', 'Statut', 'Différences'], $champs);
        $lignes = $lot->lignes()->orderBy('numero')->get()->map(fn (ImportLigne $l) => array_merge([
            $l->numero, ImportLigne::OPERATIONS[$l->operation] ?? $l->operation, $l->statut,
            collect($l->differences ?? [])->map(fn ($d, $champ) => "{$champ} : ".json_encode($d['avant'], JSON_UNESCAPED_UNICODE).' → '.json_encode($d['apres'], JSON_UNESCAPED_UNICODE))->implode(' ; '),
        ], array_map(fn ($c) => $l->donnees[$c] ?? null, $champs)));

        return $this->telecharger("operations-{$lot->reference}", $entetes, $lignes->all(), $format);
    }

    /** Modèle officiel du domaine : en-têtes attendus et lignes d'exemple. */
    public function modele(string $domaineCode): StreamedResponse
    {
        $domaine = $this->domaines->get($domaineCode);
        $entetes = array_map(fn ($c) => $c->champ, $domaine->champs());
        $lignes = array_map(fn ($ex) => array_map(fn ($c) => $ex[$c] ?? null, $entetes), $domaine->exemple());

        return $this->telecharger("modele-import-{$domaineCode}", $entetes, $lignes, 'xlsx');
    }

    /**
     * @param  list<string>  $entetes
     * @param  list<list<mixed>>  $lignes
     */
    private function telecharger(string $nom, array $entetes, array $lignes, string $format): StreamedResponse
    {
        $neutraliser = fn ($v) => Normaliseur::estFormule($v) ? "'".$v : $v;
        $lignes = array_map(fn ($l) => array_map($neutraliser, $l), $lignes);

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($entetes, $lignes) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $entetes, ';');
                foreach ($lignes as $l) {
                    fputcsv($out, $l, ';');
                }
                fclose($out);
            }, "{$nom}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($entetes, $lignes) {
            $classeur = new Spreadsheet();
            $feuille = $classeur->getActiveSheet();
            $feuille->fromArray($entetes, null, 'A1');
            $feuille->getStyle('1:1')->getFont()->setBold(true);
            foreach ($lignes as $i => $l) {
                foreach (array_values($l) as $j => $v) {
                    // Valeurs écrites comme texte explicite : aucune formule ne peut être interprétée.
                    $feuille->setCellValueExplicit([$j + 1, $i + 2], $v === null ? '' : (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            foreach (range(1, count($entetes)) as $col) {
                $feuille->getColumnDimensionByColumn($col)->setAutoSize(true);
            }
            (new Xlsx($classeur))->save('php://output');
        }, "{$nom}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
