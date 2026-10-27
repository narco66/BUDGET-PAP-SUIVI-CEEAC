<?php

namespace App\Services\Import;

use App\Models\ImportLot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Fichiers sources : stockage privé, empreinte SHA-256, contrôle du format RÉEL (signature binaire et type
 * MIME, pas seulement l'extension), puis lecture (Excel, CSV, JSON) en lignes associatives en-tête → valeur.
 * Les formules Excel ne sont jamais évaluées : elles sont lues comme du texte (et signalées à l'analyse).
 */
class ImportFichierService
{
    /** @return array{chemin: string, empreinte: string, taille: int, type: string, nom: string} */
    public function stocker(UploadedFile $fichier): array
    {
        $extension = mb_strtolower($fichier->getClientOriginalExtension());
        $formats = config('import.formats');
        if (! isset($formats[$extension])) {
            throw new RuntimeException('Format non accepté : '.implode(', ', array_keys($formats)).' uniquement.');
        }
        if ($fichier->getSize() > config('import.taille_max_mo') * 1024 * 1024) {
            throw new RuntimeException('Fichier trop volumineux (maximum '.config('import.taille_max_mo').' Mo).');
        }
        $chemin = $fichier->getRealPath();
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($chemin);
        $this->controlerSignature($chemin, $extension, $mime);

        $empreinte = hash_file('sha256', $chemin);
        $stocke = $fichier->storeAs(config('import.repertoire').'/'.now()->format('Y/m'), Str::uuid().'.'.$extension, config('import.disque'));

        return ['chemin' => $stocke, 'empreinte' => $empreinte, 'taille' => (int) $fichier->getSize(), 'type' => $mime, 'nom' => $fichier->getClientOriginalName()];
    }

    /** Le contenu correspond-il à l'extension annoncée ? Refuse exécutables et fichiers maquillés. */
    private function controlerSignature(string $chemin, string $extension, string $mime): void
    {
        $tete = (string) file_get_contents($chemin, false, null, 0, 8);
        if (str_starts_with($tete, 'MZ') || str_starts_with($tete, "\x7FELF") || str_starts_with($tete, '#!')) {
            throw new RuntimeException('Fichier refusé : contenu exécutable.');
        }
        $ok = match ($extension) {
            'xlsx' => str_starts_with($tete, "PK\x03\x04"),
            'xls' => str_starts_with($tete, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            'csv', 'json' => ! str_contains((string) file_get_contents($chemin, false, null, 0, 65536), "\0"),
            default => false,
        };
        if (! $ok || ! in_array($mime, config('import.formats')[$extension], true)) {
            throw new RuntimeException("Fichier refusé : le contenu ({$mime}) ne correspond pas à un fichier .{$extension} valide.");
        }
    }

    /**
     * Lecture du fichier d'un lot.
     *
     * @return array{entetes: list<string>, lignes: list<array{numero: int, valeurs: array<string, mixed>}>, feuilles: list<string>}
     */
    public function lire(ImportLot $lot): array
    {
        $disque = Storage::disk(config('import.disque'));
        if (! $lot->fichier_chemin || ! $disque->exists($lot->fichier_chemin)) {
            throw new RuntimeException('Fichier source introuvable.');
        }
        if (hash_file('sha256', $disque->path($lot->fichier_chemin)) !== $lot->fichier_empreinte) {
            throw new RuntimeException('Fichier source altéré : son empreinte ne correspond plus à celle du dépôt.');
        }
        $chemin = $disque->path($lot->fichier_chemin);

        return match (mb_strtolower(pathinfo($lot->fichier_chemin, PATHINFO_EXTENSION))) {
            'csv' => $this->lireCsv($chemin),
            'json' => $this->lireJson($chemin),
            default => $this->lireTableur($chemin, $lot->feuille),
        };
    }

    /** @return array{entetes: list<string>, lignes: list<array{numero: int, valeurs: array<string, mixed>}>, feuilles: list<string>} */
    private function lireTableur(string $chemin, ?string $feuille): array
    {
        try {
            $lecteur = IOFactory::createReaderForFile($chemin);
            $lecteur->setReadDataOnly(true);
            $classeur = $lecteur->load($chemin);
        } catch (\Throwable $e) {
            throw new RuntimeException('Classeur illisible ou corrompu.');
        }
        $feuilles = $classeur->getSheetNames();
        $onglet = $feuille && in_array($feuille, $feuilles, true) ? $classeur->getSheetByName($feuille) : $classeur->getSheet(0);
        // Valeurs brutes, formules NON calculées (une formule est lue comme texte).
        $tableau = $onglet->toArray(null, false, false, false);
        $classeur->disconnectWorksheets();

        return $this->structurer($tableau, $feuilles, 1);
    }

    /** @return array{entetes: list<string>, lignes: list<array{numero: int, valeurs: array<string, mixed>}>, feuilles: list<string>} */
    private function lireCsv(string $chemin): array
    {
        $contenu = (string) file_get_contents($chemin);
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);
        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }
        $premiere = strtok($contenu, "\n") ?: '';
        $separateurs = [';' => substr_count($premiere, ';'), ',' => substr_count($premiere, ','), "\t" => substr_count($premiere, "\t"), '|' => substr_count($premiere, '|')];
        arsort($separateurs);
        $separateur = (string) array_key_first($separateurs);

        $flux = fopen('php://temp', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);
        $tableau = [];
        while (($ligne = fgetcsv($flux, 0, $separateur, '"', '')) !== false) {
            $tableau[] = $ligne;
        }
        fclose($flux);

        return $this->structurer($tableau, [], 1);
    }

    /** @return array{entetes: list<string>, lignes: list<array{numero: int, valeurs: array<string, mixed>}>, feuilles: list<string>} */
    private function lireJson(string $chemin): array
    {
        $donnees = json_decode((string) file_get_contents($chemin), true);
        if (! is_array($donnees)) {
            throw new RuntimeException('JSON invalide.');
        }
        $donnees = array_is_list($donnees) ? $donnees : ($donnees['lignes'] ?? $donnees['data'] ?? null);
        if (! is_array($donnees) || ($donnees !== [] && ! is_array($donnees[0] ?? null))) {
            throw new RuntimeException('JSON attendu : une liste d\'objets (ou un objet { "lignes": [ … ] }).');
        }
        $entetes = [];
        foreach ($donnees as $objet) {
            foreach (array_keys((array) $objet) as $cle) {
                $entetes[(string) $cle] = true;
            }
        }
        $entetes = array_keys($entetes);
        $lignes = [];
        foreach (array_values($donnees) as $i => $objet) {
            $lignes[] = ['numero' => $i + 1, 'valeurs' => array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), array_replace(array_fill_keys($entetes, null), (array) $objet))];
        }
        $this->limiter(count($lignes));

        return ['entetes' => $entetes, 'lignes' => $lignes, 'feuilles' => []];
    }

    /**
     * Première ligne non vide = en-têtes (doublons suffixés) ; lignes entièrement vides ignorées.
     *
     * @param  list<list<mixed>>  $tableau
     * @param  list<string>  $feuilles
     * @return array{entetes: list<string>, lignes: list<array{numero: int, valeurs: array<string, mixed>}>, feuilles: list<string>}
     */
    private function structurer(array $tableau, array $feuilles, int $base): array
    {
        $entetes = null;
        $lignes = [];
        foreach ($tableau as $i => $ligne) {
            $vide = collect($ligne)->every(fn ($v) => $v === null || trim((string) $v) === '');
            if ($vide) {
                continue;
            }
            if ($entetes === null) {
                $entetes = [];
                foreach ($ligne as $j => $nom) {
                    $nom = trim((string) $nom) !== '' ? trim((string) $nom) : 'colonne_'.($j + 1);
                    $unique = $nom;
                    for ($k = 2; in_array($unique, $entetes, true); $k++) {
                        $unique = $nom.'_'.$k;
                    }
                    $entetes[] = $unique;
                }

                continue;
            }
            $valeurs = [];
            foreach ($entetes as $j => $nom) {
                $valeurs[$nom] = $ligne[$j] ?? null;
            }
            $lignes[] = ['numero' => $i + $base, 'valeurs' => $valeurs];
        }
        $this->limiter(count($lignes));

        return ['entetes' => $entetes ?? [], 'lignes' => $lignes, 'feuilles' => $feuilles];
    }

    private function limiter(int $nombre): void
    {
        if ($nombre > config('import.lignes_max')) {
            throw new RuntimeException("Fichier trop long ({$nombre} lignes) : maximum ".config('import.lignes_max').' lignes par lot. Scinder le fichier en lots métier indépendants.');
        }
    }
}
