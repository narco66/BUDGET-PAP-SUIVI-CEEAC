<?php

namespace App\Services\Ged;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GedStorageService
{
    public function __construct(private GedAntivirusService $antivirus) {}

    /** Extensions autorisées par défaut. */
    public function extensionsDefaut(): array
    {
        return config('ged.extensions', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv']);
    }

    public function tailleMaxMo(): int
    {
        return (int) config('ged.taille_max_mo', 10);
    }

    /**
     * Stocke un fichier dans storage/app/private/ged/{annee}/{mois}/{module}/{type}/
     *
     * @param  array<string>|null  $extensionsAutorisees
     * @return array{chemin: string, hash: string, taille: int, mime: string, extension: string, nom_original: string}
     */
    public function stocker(
        UploadedFile $fichier,
        string $module = 'GED',
        string $type = 'DOC',
        ?array $extensionsAutorisees = null,
        ?int $tailleMaxMo = null,
    ): array {
        $extensions = $extensionsAutorisees ?? $this->extensionsDefaut();
        $tailleMaxMo = $tailleMaxMo ?? $this->tailleMaxMo();
        $ext = strtolower($fichier->getClientOriginalExtension());

        if (! in_array($ext, $extensions, true)) {
            throw new \InvalidArgumentException("Extension « {$ext} » non autorisée.");
        }

        if ($fichier->getSize() > $tailleMaxMo * 1024 * 1024) {
            throw new \InvalidArgumentException("Fichier trop volumineux (max {$tailleMaxMo} Mo).");
        }

        $cheminRelatif = sprintf(
            'ged/%s/%s/%s/%s/%s',
            now()->format('Y'),
            now()->format('m'),
            Str::slug($module, '_'),
            Str::slug($type, '_'),
            Str::uuid()->toString().'.'.$ext,
        );

        $contenu = file_get_contents($fichier->getRealPath());
        $hash = hash('sha256', $contenu);

        Storage::disk('local')->put($cheminRelatif, $contenu);

        $cheminAbsolu = Storage::disk('local')->path($cheminRelatif);

        try {
            $this->antivirus->verifier($cheminAbsolu);
        } catch (\RuntimeException $e) {
            $this->mettreEnQuarantaine($cheminRelatif, $contenu);
            throw $e;
        }

        return [
            'chemin' => $cheminRelatif,
            'hash' => $hash,
            'taille' => $fichier->getSize(),
            'mime' => $fichier->getClientMimeType() ?? 'application/octet-stream',
            'extension' => $ext,
            'nom_original' => $fichier->getClientOriginalName(),
        ];
    }

    public function existe(string $chemin): bool
    {
        return Storage::disk('local')->exists($chemin);
    }

    public function contenu(string $chemin): ?string
    {
        if (! $this->existe($chemin)) {
            return null;
        }

        return Storage::disk('local')->get($chemin);
    }

    public function verifierIntegrite(string $chemin, string $hashAttendu): bool
    {
        $contenu = $this->contenu($chemin);

        return $contenu !== null && hash_equals($hashAttendu, hash('sha256', $contenu));
    }

    private function mettreEnQuarantaine(string $cheminRelatif, string $contenu): void
    {
        if (! config('ged.antivirus.quarantine', true)) {
            Storage::disk('local')->delete($cheminRelatif);

            return;
        }

        $quarantaine = sprintf(
            'ged/quarantine/%s/%s',
            now()->format('Y/m'),
            basename($cheminRelatif)
        );

        Storage::disk('local')->put($quarantaine, $contenu);
        Storage::disk('local')->delete($cheminRelatif);
    }
}
