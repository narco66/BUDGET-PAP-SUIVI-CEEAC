<?php

namespace App\Services\Pap;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Pap;
use App\Models\PapHistorique;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use App\Services\Budget\RbmBudgetSyncService;
use App\Support\AuditLogger;
use App\Support\CockpitCache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class PapReplacementImportService
{
    private const HEADERS = [
        'pilier_code',
        'pilier_intitule',
        'axe_code',
        'axe_intitule',
        'produit_code',
        'produit_intitule',
        'sous_produit_code',
        'sous_produit_intitule',
        'activite_code',
        'activite_intitule',
        'montant_ceeac',
        'montant_ptf',
        'ligne_code',
    ];

    public function __construct(
        private readonly PapSnapshotService $snapshot,
        private readonly RbmBudgetSyncService $budgetSync,
        private readonly PapPerformanceService $performance,
        private readonly PapReferentielService $referentiel,
    ) {
    }

    /** @return array<string,mixed> */
    public function analyser(string $fichierPap, ?string $modele = null): array
    {
        $this->assertReadableFile($fichierPap, 'fichier PAP');
        if ($modele) {
            $this->assertReadableFile($modele, 'modele PAP');
        }

        $modeleHeaders = $modele ? $this->headers($modele) : self::HEADERS;
        $headers = $this->headers($fichierPap);
        $rows = $this->rows($fichierPap);
        $controle = $this->rapportControle($fichierPap);
        $stats = $this->stats($rows);

        $erreurs = [];
        if ($headers !== self::HEADERS) {
            $erreurs[] = 'Les en-tetes du fichier PAP ne correspondent pas au modele applicatif attendu.';
        }
        if ($modeleHeaders !== self::HEADERS) {
            $erreurs[] = 'Le fichier modele fourni ne correspond pas au modele applicatif attendu.';
        }

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            foreach (['pilier_code', 'axe_code', 'produit_code', 'sous_produit_code', 'activite_code'] as $field) {
                if ($row[$field] === '') {
                    $erreurs[] = "Ligne {$line} : {$field} est obligatoire.";
                }
            }
            if ($row['montant_ceeac'] < 0 || $row['montant_ptf'] < 0) {
                $erreurs[] = "Ligne {$line} : les montants ne peuvent pas etre negatifs.";
            }
        }

        $ecartsControle = $this->ecartsControle($controle, $stats);

        return [
            'fichier' => $fichierPap,
            'modele' => $modele,
            'headers' => $headers,
            'modele_headers' => $modeleHeaders,
            'lignes' => count($rows),
            'stats' => $stats,
            'rapport_controle' => $controle,
            'ecarts_controle' => $ecartsControle,
            'corrections_codes' => $this->correctionsCodes($rows),
            'erreurs' => $erreurs,
            'valide' => $erreurs === [],
            'niveaux_absents_source' => [
                'taches' => ! in_array('tache_code', $headers, true),
                'indicateurs' => ! in_array('indicateur_code', $headers, true),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function remplacer(string $fichierPap, int $exerciceId, ?string $modele = null, ?int $userId = null): array
    {
        $analyse = $this->analyser($fichierPap, $modele);
        if (! $analyse['valide']) {
            throw new RuntimeException('Import PAP refuse : '.implode(' | ', $analyse['erreurs']));
        }

        $rows = $this->rows($fichierPap);
        $exercice = Exercice::findOrFail($exerciceId);

        return DB::transaction(function () use ($rows, $exercice, $fichierPap, $userId, $analyse) {
            $pap = Pap::firstOrCreate(
                ['exercice_id' => $exercice->id],
                [
                    'reference' => 'PAP-'.$exercice->annee,
                    'libelle' => 'Plan Annuel de Performance '.$exercice->annee,
                    'statut' => 'brouillon',
                    'version' => 1,
                    'cree_par' => $userId,
                    'date_elaboration' => now()->toDateString(),
                ]
            );

            $historique = $this->archiverSnapshot($pap->fresh(), $fichierPap, $userId, $analyse);
            $archive = $this->archiverReferentiel($exercice->id);
            $creation = $this->creerReferentiel($rows, $exercice, $pap, $userId);

            $pap->update([
                'reference' => $pap->reference ?: 'PAP-'.$exercice->annee,
                'libelle' => 'Plan Annuel de Performance '.$exercice->annee,
                'statut' => 'brouillon',
                'version' => ((int) $pap->version) + 1,
                'date_elaboration' => now()->toDateString(),
                'observations' => 'Remplacement complet depuis '.basename($fichierPap).' le '.now()->format('Y-m-d H:i:s').'.',
                'motif_rejet' => null,
                'validated_at' => null,
                'validated_by' => null,
                'publie_le' => null,
            ]);

            $liens = $this->referentiel->synchroniserLiensPiliers($exercice->id);
            $taux = $this->performance->synchroniserTauxFinanciersExercice($exercice->id);
            CockpitCache::bump($exercice->id, ['pap', 'budget']);

            AuditLogger::enregistrer('pap_remplacement_import_excel', $pap->fresh(), null, [
                'fichier' => $fichierPap,
                'exercice_id' => $exercice->id,
                'historique_id' => $historique->id,
                'archive' => $archive,
                'creation' => $creation,
            ]);

            return [
                'pap_id' => $pap->id,
                'exercice_id' => $exercice->id,
                'historique_id' => $historique->id,
                'archive' => $archive,
                'creation' => $creation,
                'liens_piliers_synchronises' => $liens,
                'taux_financiers_synchronises' => $taux,
                'audit_apres_import' => [
                    'execution' => $this->performance->executionPap($exercice->id),
                ],
            ];
        });
    }

    private function archiverSnapshot(Pap $pap, string $fichierPap, ?int $userId, array $analyse): PapHistorique
    {
        return PapHistorique::create([
            'pap_id' => $pap->id,
            'version' => $pap->version,
            'action' => 'archive_avant_remplacement_excel',
            'snapshot' => array_merge($this->snapshot->capturer($pap), [
                'import_remplacement' => [
                    'fichier' => $fichierPap,
                    'capture_avant_remplacement' => now()->toIso8601String(),
                    'analyse_source' => $analyse['stats'],
                ],
            ]),
            'snapshot_complet' => true,
            'motif' => 'Archive complete avant remplacement du PAP par import Excel.',
            'user_id' => $userId,
        ]);
    }

    /** @return array<string,int> */
    private function archiverReferentiel(int $exerciceId): array
    {
        $now = now();
        $pilierIds = Pilier::query()->where('exercice_id', $exerciceId)->pluck('id');
        if ($pilierIds->isEmpty()) {
            return [
                'piliers' => 0,
                'axes' => 0,
                'produits' => 0,
                'sous_produits' => 0,
                'activites' => 0,
                'taches' => 0,
                'lignes_budgetaires_detachees' => 0,
            ];
        }

        $axeIds = Axe::query()->whereIn('pilier_id', $pilierIds)->pluck('id');
        $produitIds = Produit::query()->whereIn('axe_id', $axeIds)->pluck('id');
        $sousProduitIds = SousProduit::query()->whereIn('produit_id', $produitIds)->pluck('id');
        $activiteIds = Activite::query()->whereIn('sous_produit_id', $sousProduitIds)->pluck('id');
        $tacheIds = Tache::query()->whereIn('activite_id', $activiteIds)->pluck('id');

        $detachees = $activiteIds->isEmpty()
            ? 0
            : LigneBudgetaire::query()->whereIn('activite_id', $activiteIds)->update(['activite_id' => null]);

        foreach ($tacheIds as $id) {
            Tache::withTrashed()->whereKey($id)->update([
                'code' => 'ARCH-'.$id,
                'statut_referentiel' => 'archive',
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($activiteIds as $id) {
            Activite::withTrashed()->whereKey($id)->update([
                'code' => 'ARCH-'.$id,
                'ligne_budgetaire_id' => null,
                'statut' => 'archive',
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($sousProduitIds as $id) {
            SousProduit::withTrashed()->whereKey($id)->update([
                'code' => 'ARCH-'.$id,
                'statut' => 'archive',
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($produitIds as $id) {
            Produit::withTrashed()->whereKey($id)->update([
                'code' => 'ARCH-'.$id,
                'statut' => 'archive',
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($axeIds as $id) {
            Axe::withTrashed()->whereKey($id)->update([
                'code' => 'ARCH-'.$id,
                'statut' => 'archive',
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($pilierIds as $id) {
            Pilier::withTrashed()->whereKey($id)->update([
                'code' => 'ARCH-'.$id,
                'statut' => 'archive',
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [
            'piliers' => $pilierIds->count(),
            'axes' => $axeIds->count(),
            'produits' => $produitIds->count(),
            'sous_produits' => $sousProduitIds->count(),
            'activites' => $activiteIds->count(),
            'taches' => $tacheIds->count(),
            'lignes_budgetaires_detachees' => $detachees,
        ];
    }

    /** @return array<string,mixed> */
    private function creerReferentiel(array $rows, Exercice $exercice, Pap $pap, ?int $userId): array
    {
        $piliers = [];
        $axes = [];
        $produits = [];
        $sousProduits = [];
        $ligneIds = [];
        $stats = [
            'piliers' => 0,
            'axes' => 0,
            'produits' => 0,
            'sous_produits' => 0,
            'activites' => 0,
            'taches' => 0,
            'indicateurs' => 0,
            'lignes_budgetaires_liees' => 0,
            'lignes_budgetaires_introuvables' => 0,
            'montant_ceeac' => 0.0,
            'montant_ptf' => 0.0,
            'montant_total' => 0.0,
        ];

        foreach ($rows as $row) {
            $pilierKey = $row['pilier_code'];
            if (! isset($piliers[$pilierKey])) {
                $piliers[$pilierKey] = Pilier::create([
                    'exercice_id' => $exercice->id,
                    'pap_id' => $pap->id,
                    'code' => $row['pilier_code'],
                    'intitule' => $this->label($row['pilier_intitule'] ?: $row['pilier_code']),
                    'description' => $this->fullTextIfTruncated($row['pilier_intitule']),
                    'ordre' => ++$stats['piliers'],
                    'statut' => 'actif',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $axeKey = $pilierKey.'|'.$row['axe_code'];
            if (! isset($axes[$axeKey])) {
                $axes[$axeKey] = Axe::create([
                    'pilier_id' => $piliers[$pilierKey]->id,
                    'code' => $row['axe_code'],
                    'intitule' => $this->label($row['axe_intitule'] ?: $row['axe_code']),
                    'objectif_strategique' => $this->fullTextIfTruncated($row['axe_intitule']),
                    'ordre' => ++$stats['axes'],
                    'statut' => 'actif',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $produitKey = $axeKey.'|'.$row['produit_code'];
            if (! isset($produits[$produitKey])) {
                $produits[$produitKey] = Produit::create([
                    'axe_id' => $axes[$axeKey]->id,
                    'code' => $row['produit_code'],
                    'intitule' => $this->label($row['produit_intitule'] ?: $row['produit_code']),
                    'description' => $this->fullTextIfTruncated($row['produit_intitule']),
                    'ordre' => ++$stats['produits'],
                    'statut' => 'actif',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $sousProduitKey = $produitKey.'|'.$row['sous_produit_code'];
            if (! isset($sousProduits[$sousProduitKey])) {
                $sousProduits[$sousProduitKey] = SousProduit::create([
                    'produit_id' => $produits[$produitKey]->id,
                    'code' => $row['sous_produit_code'],
                    'intitule' => $this->label($row['sous_produit_intitule'] ?: $row['sous_produit_code']),
                    'description' => $this->fullTextIfTruncated($row['sous_produit_intitule']),
                    'ordre' => ++$stats['sous_produits'],
                    'statut' => 'actif',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $ligneId = null;
            if ($row['ligne_code'] !== '') {
                $ligneId = LigneBudgetaire::query()
                    ->where('exercice_id', $exercice->id)
                    ->where('code', $row['ligne_code'])
                    ->value('id');

                if ($ligneId) {
                    $stats['lignes_budgetaires_liees']++;
                    $ligneIds[] = $ligneId;
                } else {
                    $stats['lignes_budgetaires_introuvables']++;
                }
            }

            $montantTotal = $row['montant_ceeac'] + $row['montant_ptf'];
            $activite = Activite::create([
                'sous_produit_id' => $sousProduits[$sousProduitKey]->id,
                'produit_id' => $produits[$produitKey]->id,
                'ligne_budgetaire_id' => $ligneId,
                'code' => $row['activite_code'],
                'intitule' => $this->label($row['activite_intitule'] ?: $row['activite_code'], 1000),
                'description' => $this->fullTextIfTruncated($row['activite_intitule'], 1000),
                'montant_ceeac' => $row['montant_ceeac'],
                'montant_ptf' => $row['montant_ptf'],
                'montant_total' => $montantTotal,
                'taux_realisation_physique' => 0,
                'taux_realisation_financier' => 0,
                'ordre' => ++$stats['activites'],
                'statut' => 'actif',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $stats['montant_ceeac'] += $row['montant_ceeac'];
            $stats['montant_ptf'] += $row['montant_ptf'];
            $stats['montant_total'] += $montantTotal;

            if ($ligneId) {
                $this->budgetSync->synchroniserActivite($activite->fresh());
            }
        }

        $this->performance->synchroniserDepuisLignes($ligneIds);

        $stats['montant_ceeac'] = round($stats['montant_ceeac'], 2);
        $stats['montant_ptf'] = round($stats['montant_ptf'], 2);
        $stats['montant_total'] = round($stats['montant_total'], 2);

        return $stats;
    }

    /** @return list<string> */
    private function headers(string $path): array
    {
        $sheet = IOFactory::load($path)->getSheetByName('Worksheet') ?? IOFactory::load($path)->getSheet(0);
        $highestColumn = $sheet->getHighestDataColumn();
        $values = $sheet->rangeToArray('A1:'.$highestColumn.'1', null, true, false)[0] ?? [];

        return array_map(fn ($value) => $this->normalizeHeader($value), $values);
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Worksheet') ?? $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $headers = $this->headers($path);
        $rawRows = $highestRow >= 2
            ? $sheet->rangeToArray('A2:'.$highestColumn.$highestRow, null, true, false)
            : [];

        $rows = [];
        foreach ($rawRows as $raw) {
            if (collect($raw)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = $raw[$index] ?? null;
            }

            $normalized = [];
            foreach (self::HEADERS as $header) {
                $normalized[$header] = in_array($header, ['montant_ceeac', 'montant_ptf'], true)
                    ? $this->parseAmount($row[$header] ?? 0)
                    : trim((string) ($row[$header] ?? ''));
            }

            $rows[] = $normalized;
        }

        return $this->normaliserDoublonsActivites($rows);
    }

    /** @return list<array<string,mixed>> */
    private function normaliserDoublonsActivites(array $rows): array
    {
        $seen = [];

        foreach ($rows as $index => $row) {
            $scope = implode('|', [
                $row['pilier_code'],
                $row['axe_code'],
                $row['produit_code'],
                $row['sous_produit_code'],
                $row['activite_code'],
            ]);

            $seen[$scope] = ($seen[$scope] ?? 0) + 1;
            if ($seen[$scope] <= 1) {
                continue;
            }

            $original = $row['activite_code'];
            $suffix = '-DUP'.$seen[$scope];
            $rows[$index]['activite_code'] = substr($original, 0, 40 - strlen($suffix)).$suffix;
            $rows[$index]['activite_code_original'] = $original;
            $rows[$index]['activite_code_corrige'] = $rows[$index]['activite_code'];
            $rows[$index]['ligne_excel'] = $index + 2;
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function correctionsCodes(array $rows): array
    {
        return collect($rows)
            ->filter(fn (array $row) => isset($row['activite_code_original']))
            ->map(fn (array $row) => [
                'ligne_excel' => $row['ligne_excel'],
                'niveau' => 'activite',
                'code_original' => $row['activite_code_original'],
                'code_importe' => $row['activite_code_corrige'],
            ])
            ->values()
            ->all();
    }

    /** @return array<string,mixed> */
    private function stats(array $rows): array
    {
        $seen = [
            'piliers' => [],
            'axes' => [],
            'produits' => [],
            'sous_produits' => [],
        ];
        $montantCeeac = 0.0;
        $montantPtf = 0.0;

        foreach ($rows as $row) {
            $seen['piliers'][$row['pilier_code']] = true;
            $seen['axes'][$row['pilier_code'].'|'.$row['axe_code']] = true;
            $seen['produits'][$row['pilier_code'].'|'.$row['axe_code'].'|'.$row['produit_code']] = true;
            $seen['sous_produits'][$row['pilier_code'].'|'.$row['axe_code'].'|'.$row['produit_code'].'|'.$row['sous_produit_code']] = true;
            $montantCeeac += $row['montant_ceeac'];
            $montantPtf += $row['montant_ptf'];
        }

        return [
            'lignes_pap' => count($rows),
            'piliers' => count($seen['piliers']),
            'axes' => count($seen['axes']),
            'produits' => count($seen['produits']),
            'sous_produits' => count($seen['sous_produits']),
            'activites' => count($rows),
            'taches' => 0,
            'indicateurs' => 0,
            'montant_ceeac' => round($montantCeeac, 2),
            'montant_ptf' => round($montantPtf, 2),
            'montant_total' => round($montantCeeac + $montantPtf, 2),
        ];
    }

    /** @return array<string,mixed> */
    private function rapportControle(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Rapport_controle');
        if (! $sheet) {
            return [];
        }

        $rows = $sheet->rangeToArray('A1:B'.$sheet->getHighestDataRow(), null, true, false);
        $controle = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row[0] ?? ''));
            if ($key === '') {
                continue;
            }
            $controle[$key] = $row[1] ?? null;
        }

        return $controle;
    }

    /** @return array<string,array{attendu:mixed,calcule:mixed}> */
    private function ecartsControle(array $controle, array $stats): array
    {
        $map = [
            'Nombre de lignes PAP transferees' => 'lignes_pap',
            'Nombre de lignes PAP transférées' => 'lignes_pap',
            'Nombre de piliers detectes' => 'piliers',
            'Nombre de piliers détectés' => 'piliers',
            'Nombre d\'axes detectes' => 'axes',
            'Nombre d\'axes détectés' => 'axes',
            'Nombre de produits detectes' => 'produits',
            'Nombre de produits détectés' => 'produits',
            'Montant CEEAC total transfere' => 'montant_ceeac',
            'Montant CEEAC total transféré' => 'montant_ceeac',
        ];

        $ecarts = [];
        foreach ($map as $label => $statKey) {
            if (! array_key_exists($label, $controle)) {
                continue;
            }
            $attendu = is_numeric($controle[$label]) ? (float) $controle[$label] : $controle[$label];
            $calcule = $stats[$statKey];
            if (is_numeric($attendu) && is_numeric($calcule)) {
                if (abs((float) $attendu - (float) $calcule) > 0.01) {
                    $ecarts[$label] = ['attendu' => $attendu, 'calcule' => $calcule];
                }
            } elseif ($attendu !== $calcule) {
                $ecarts[$label] = ['attendu' => $attendu, 'calcule' => $calcule];
            }
        }

        return $ecarts;
    }

    private function parseAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $raw = preg_replace('/[^\d,.\-]/', '', str_replace(["\xc2\xa0", ' '], '', (string) $value));
        if ($raw === '' || $raw === '-' || $raw === null) {
            return 0.0;
        }

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $raw)) {
            $raw = str_replace(',', '', $raw);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $raw)) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',') && ! str_contains($raw, '.')) {
            $raw = str_replace(',', '.', $raw);
        } elseif (substr_count($raw, ',') > 0 && substr_count($raw, '.') > 0) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');
            if ($lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        }

        return round((float) $raw, 2);
    }

    private function label(string $value, int $max = 255): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ($this->length($value) <= $max) {
            return $value;
        }

        return rtrim($this->slice($value, 0, $max - 3)).'...';
    }

    private function fullTextIfTruncated(string $value, int $max = 255): ?string
    {
        $value = trim($value);

        return $this->length($value) > $max ? $value : null;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function slice(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, $start, $length) : substr($value, $start, $length);
    }

    private function normalizeHeader(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function assertReadableFile(string $path, string $label): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Le {$label} est introuvable ou illisible : {$path}");
        }
    }
}
