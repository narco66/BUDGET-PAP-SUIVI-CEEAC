<?php

namespace App\Services\Ged;

use App\Enums\Ged\DocumentStatut;
use App\Enums\Ged\NiveauConfidentialite;
use App\Enums\Ged\SignatureStatut;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedVersion;
use App\Models\PieceJustificative;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Intégration automatique à la GED de tout document joint (pièces des dossiers) et de tout document généré
 * (PDF institutionnels, exports, états). Le fichier n'est pas dupliqué lorsqu'il est déjà stocké : le document
 * GED le référence ; il porte le module et le dossier d'origine, l'empreinte SHA-256 et une version initiale.
 */
class GedIntegrationAutoService
{
    public function __construct(
        private readonly GedReferenceService $references,
        private readonly GedJournalService $journal,
    ) {}

    /** Indexe une pièce justificative dans la GED (idempotent). */
    public function indexerPiece(PieceJustificative $piece): GedDocument
    {
        if ($piece->ged_document_id && ($existant = GedDocument::find($piece->ged_document_id))) {
            return $existant;
        }
        $dossier = $piece->attachable;
        $genere = $piece->categorie === 'document_genere';

        $document = $this->creer([
            'chemin' => $piece->chemin,
            'nom' => $piece->nom,
            'mime' => $piece->type_mime ?: 'application/octet-stream',
            'taille' => (int) $piece->taille,
            'hash' => $this->empreinte($piece->chemin),
            'type_code' => $genere ? 'DOCGEN' : 'PIECE',
            'description' => ($genere ? 'Document généré' : 'Pièce justificative').($dossier ? ' du dossier '.$this->libelleDossier($dossier) : '')
                .($piece->categorie && ! $genere ? ' — '.$piece->categorie : ''),
            'meta' => ['origine' => $genere ? 'document_genere' : 'piece_jointe', 'piece_id' => $piece->id, 'categorie' => $piece->categorie],
        ], $dossier, $piece->televerse_par ? User::find($piece->televerse_par) : null, 'indexation_piece');

        DB::table('pieces_justificatives')->where('id', $piece->id)->update(['ged_document_id' => $document->id]);
        $piece->ged_document_id = $document->id;

        return $document;
    }

    /**
     * Archive dans la GED un document généré à la volée (contenu binaire). Ignoré si un document identique
     * (même empreinte, même dossier) y figure déjà.
     *
     * @param  array<string, mixed>  $meta
     */
    public function archiverGenere(string $contenu, string $nomFichier, string $mime, ?Model $dossier, ?User $user, array $meta = []): ?GedDocument
    {
        $hash = hash('sha256', $contenu);
        $doublon = GedDocument::query()->where('hash_sha256', $hash)
            ->when($dossier, fn ($q) => $q->where('attachable_type', $dossier::class)->where('attachable_id', $dossier->getKey()))
            ->exists();
        if ($doublon) {
            return null;
        }

        $nom = $this->nomFichierSur($nomFichier);
        $chemin = 'ged/generes/'.now()->format('Y/m').'/'.Str::uuid().'_'.$nom;
        Storage::disk('local')->put($chemin, $contenu);

        return $this->creer([
            'chemin' => $chemin,
            'nom' => $nom,
            'mime' => $mime,
            'taille' => strlen($contenu),
            'hash' => $hash,
            'type_code' => 'DOCGEN',
            'description' => 'Document généré'.($dossier ? ' pour le dossier '.$this->libelleDossier($dossier) : '').(isset($meta['route']) ? ' ('.$meta['route'].')' : ''),
            'meta' => ['origine' => 'document_genere'] + $meta,
        ], $dossier, $user, 'indexation_document_genere');
    }

    /** @param  array<string, mixed>  $f */
    private function creer(array $f, ?Model $dossier, ?User $user, string $action): GedDocument
    {
        $module = $dossier ? $this->moduleDe($dossier) : 'EXPORT';
        $extension = strtolower(pathinfo($f['nom'], PATHINFO_EXTENSION)) ?: 'bin';

        $document = DB::transaction(function () use ($f, $dossier, $user, $module, $extension) {
            $doc = GedDocument::create([
                'uuid' => (string) Str::uuid(),
                'reference' => $this->references->generer($module, $f['type_code']),
                'titre' => Str::limit(pathinfo($f['nom'], PATHINFO_FILENAME), 250, ''),
                'description' => Str::limit($f['description'], 1000, ''),
                'module_source' => $module,
                'module_source_id' => $dossier?->getKey(),
                'attachable_type' => $dossier ? $dossier::class : null,
                'attachable_id' => $dossier?->getKey(),
                'chemin_fichier' => $f['chemin'],
                'nom_original' => $f['nom'],
                'extension' => $extension,
                'mime_type' => $f['mime'],
                'taille' => $f['taille'],
                'hash_sha256' => $f['hash'],
                'version_courante' => 1,
                'statut' => DocumentStatut::Depose,
                'niveau_confidentialite' => NiveauConfidentialite::Interne,
                'date_document' => now()->toDateString(),
                'date_depot' => now(),
                'exercice_id' => $dossier?->getAttribute('exercice_id'),
                'structure_id' => $dossier?->getAttribute('structure_id') ?? $user?->structure_id,
                'signature_status' => SignatureStatut::NonRequis,
                'metadonnees_metier' => $f['meta'],
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);
            GedVersion::create([
                'document_id' => $doc->id,
                'numero' => 1,
                'chemin_fichier' => $f['chemin'],
                'nom_original' => $f['nom'],
                'extension' => $extension,
                'mime_type' => $f['mime'],
                'taille' => $f['taille'],
                'hash_sha256' => $f['hash'],
                'statut' => 'actif',
                'commentaire' => 'Intégration automatique',
                'created_by' => $user?->id,
            ]);

            return $doc;
        });

        $this->journal->enregistrer($action, $document, $user, null, ['reference' => $document->reference] + $f['meta']);

        return $document;
    }

    /** Code module GED du dossier (BESOIN, ENGAGEMENT, LIQUIDATION…), à défaut le nom de sa classe. */
    public function moduleDe(Model $dossier): string
    {
        $type = array_search($dossier::class, GedAttachmentService::MODULES, true);

        return $type !== false ? GedAttachmentService::codeModule($type) : strtoupper(class_basename($dossier));
    }

    private function libelleDossier(Model $dossier): string
    {
        return (string) ($dossier->getAttribute('numero') ?? $dossier->getAttribute('reference') ?? $dossier->getAttribute('code') ?? class_basename($dossier).' #'.$dossier->getKey());
    }

    private function empreinte(string $chemin): ?string
    {
        $disque = Storage::disk('local');

        return $disque->exists($chemin) ? hash('sha256', (string) $disque->get($chemin)) : null;
    }

    private function nomFichierSur(string $nom): string
    {
        $nom = trim(str_replace(['/', '\\', "\0"], '_', $nom));

        return Str::limit($nom !== '' ? $nom : 'document', 180, '');
    }
}
