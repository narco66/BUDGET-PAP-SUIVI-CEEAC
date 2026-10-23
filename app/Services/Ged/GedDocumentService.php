<?php

namespace App\Services\Ged;

use App\Enums\Ged\DocumentStatut;
use App\Enums\Ged\NiveauConfidentialite;
use App\Enums\Ged\SignatureStatut;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedNumerisation;
use App\Models\Ged\GedTag;
use App\Models\Ged\GedVersion;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GedDocumentService
{
    public function __construct(
        private GedStorageService $storage,
        private GedReferenceService $references,
        private GedJournalService $journal,
        private GedOcrService $ocr,
        private GedNotificationService $notifications,
        private AccessScopeService $scopes,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function deposer(UploadedFile $fichier, array $donnees, User $user): GedDocument
    {
        $module = $donnees['module_source'] ?? 'GED';
        $typeCode = $donnees['type_code'] ?? 'DOC';

        $stockage = $this->storage->stocker($fichier, $module, $typeCode);
        $reference = $this->references->generer($module, $typeCode);

        $document = DB::transaction(function () use ($donnees, $user, $stockage, $reference) {
            $doc = GedDocument::create([
                'uuid' => (string) Str::uuid(),
                'reference' => $reference,
                'titre' => $this->titreDocument($donnees['titre'] ?? null, $stockage['nom_original']),
                'description' => $donnees['description'] ?? null,
                'categorie_id' => $donnees['categorie_id'] ?? null,
                'type_document_id' => $donnees['type_document_id'] ?? null,
                'dossier_id' => $donnees['dossier_id'] ?? null,
                'module_source' => $donnees['module_source'] ?? null,
                'module_source_id' => $donnees['module_source_id'] ?? null,
                'attachable_type' => $donnees['attachable_type'] ?? null,
                'attachable_id' => $donnees['attachable_id'] ?? null,
                'chemin_fichier' => $stockage['chemin'],
                'nom_original' => $stockage['nom_original'],
                'extension' => $stockage['extension'],
                'mime_type' => $stockage['mime'],
                'taille' => $stockage['taille'],
                'hash_sha256' => $stockage['hash'],
                'version_courante' => 1,
                'statut' => DocumentStatut::Depose,
                'niveau_confidentialite' => $donnees['niveau_confidentialite'] ?? NiveauConfidentialite::Interne,
                'date_document' => $donnees['date_document'] ?? now()->toDateString(),
                'date_depot' => now(),
                'date_expiration' => $donnees['date_expiration'] ?? null,
                'exercice_id' => $donnees['exercice_id'] ?? null,
                'structure_id' => $donnees['structure_id'] ?? $user->structure_id,
                'signature_status' => SignatureStatut::NonRequis,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            GedVersion::create([
                'document_id' => $doc->id,
                'numero' => 1,
                'chemin_fichier' => $stockage['chemin'],
                'nom_original' => $stockage['nom_original'],
                'extension' => $stockage['extension'],
                'mime_type' => $stockage['mime'],
                'taille' => $stockage['taille'],
                'hash_sha256' => $stockage['hash'],
                'statut' => 'actif',
                'commentaire' => 'Version initiale',
                'created_by' => $user->id,
            ]);

            GedNumerisation::create([
                'document_id' => $doc->id,
                'numerise_par' => $user->id,
                'source' => 'upload',
            ]);

            if (! empty($donnees['tags'])) {
                $this->synchroniserTags($doc, $donnees['tags']);
            }

            $ocr = $this->ocr->extraire($stockage['chemin'], $stockage['mime'], $stockage['extension']);
            if ($ocr) {
                $doc->update(['contenu_ocr' => $ocr]);
                GedNumerisation::where('document_id', $doc->id)->update(['ocr_applique' => true]);
            }

            return $doc;
        });

        $this->journal->enregistrer('creation', $document, $user, null, ['reference' => $document->reference]);
        $this->notifications->documentDepose($document, $user);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function mettreAJour(GedDocument $document, array $donnees, User $user): GedDocument
    {
        abort_unless($document->estModifiable(), 422, 'Document verrouillé ou archivé.');

        $ancien = $document->only(['titre', 'description', 'statut', 'niveau_confidentialite']);

        $document->fill([
            'titre' => $donnees['titre'] ?? $document->titre,
            'description' => $donnees['description'] ?? $document->description,
            'categorie_id' => $donnees['categorie_id'] ?? $document->categorie_id,
            'type_document_id' => $donnees['type_document_id'] ?? $document->type_document_id,
            'dossier_id' => $donnees['dossier_id'] ?? $document->dossier_id,
            'niveau_confidentialite' => $donnees['niveau_confidentialite'] ?? $document->niveau_confidentialite,
            'date_document' => $donnees['date_document'] ?? $document->date_document,
            'date_expiration' => $donnees['date_expiration'] ?? $document->date_expiration,
            'updated_by' => $user->id,
        ])->save();

        if (isset($donnees['tags'])) {
            $this->synchroniserTags($document, $donnees['tags']);
        }

        $this->journal->enregistrer('modification', $document, $user, $ancien, $document->only(array_keys($ancien)));

        return $document->fresh();
    }

    public function nouvelleVersion(GedDocument $document, UploadedFile $fichier, User $user, ?string $commentaire = null): GedVersion
    {
        abort_unless($document->estModifiable(), 422, 'Document verrouillé ou archivé.');

        $module = $document->module_source ?? 'GED';
        $type = $document->typeDocument?->code ?? 'DOC';
        $stockage = $this->storage->stocker($fichier, $module, $type);
        $numero = $document->version_courante + 1;

        $version = DB::transaction(function () use ($document, $stockage, $numero, $user, $commentaire) {
            $v = GedVersion::create([
                'document_id' => $document->id,
                'numero' => $numero,
                'chemin_fichier' => $stockage['chemin'],
                'nom_original' => $stockage['nom_original'],
                'extension' => $stockage['extension'],
                'mime_type' => $stockage['mime'],
                'taille' => $stockage['taille'],
                'hash_sha256' => $stockage['hash'],
                'statut' => 'actif',
                'commentaire' => $commentaire,
                'created_by' => $user->id,
            ]);

            $document->update([
                'chemin_fichier' => $stockage['chemin'],
                'nom_original' => $stockage['nom_original'],
                'extension' => $stockage['extension'],
                'mime_type' => $stockage['mime'],
                'taille' => $stockage['taille'],
                'hash_sha256' => $stockage['hash'],
                'version_courante' => $numero,
                'updated_by' => $user->id,
            ]);

            return $v;
        });

        $this->journal->enregistrer('version', $document, $user, null, ['numero' => $numero]);

        return $version;
    }

    /** @param  array<int, string>|string  $tags */
    private function synchroniserTags(GedDocument $document, array|string $tags): void
    {
        $noms = is_array($tags) ? $tags : explode(',', $tags);
        $ids = [];

        foreach ($noms as $nom) {
            $nom = trim($nom);
            if ($nom === '') {
                continue;
            }
            $tag = GedTag::firstOrCreate(['nom' => $nom]);
            $ids[] = $tag->id;
        }

        $document->tags()->sync($ids);
    }

    private function titreDocument(?string $titre, string $nomOriginal): string
    {
        $titre = trim((string) $titre);

        return $titre !== '' ? $titre : $nomOriginal;
    }

    public function scopeAccessible(Builder $query, User $user): Builder
    {
        if ($user->can('ged.admin')) {
            return $query;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('niveau_confidentialite', NiveauConfidentialite::Public)
                ->orWhere('niveau_confidentialite', NiveauConfidentialite::Interne);
        });

        if (! $this->scopes->estGlobal($user)) {
            $ids = $this->scopes->structuresAutorisees($user);
            $query->where(function (Builder $q) use ($ids) {
                $q->whereIn('structure_id', $ids)->orWhereNull('structure_id');
            });
        }

        return $query;
    }
}
