<?php

namespace App\Models\Ged;

use App\Enums\Ged\DocumentStatut;
use App\Enums\Ged\NiveauConfidentialite;
use App\Enums\Ged\SignatureStatut;
use App\Models\Exercice;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GedDocument extends Model
{
    use SoftDeletes;

    protected $table = 'ged_documents';

    protected $fillable = [
        'uuid', 'reference', 'titre', 'description', 'categorie_id', 'type_document_id', 'dossier_id',
        'module_source', 'module_source_id', 'attachable_type', 'attachable_id',
        'chemin_fichier', 'nom_original', 'extension', 'mime_type', 'taille', 'hash_sha256',
        'version_courante', 'statut', 'niveau_confidentialite', 'date_document', 'date_depot',
        'date_expiration', 'date_archivage', 'est_archive', 'est_verrouille', 'est_signe',
        'signature_status', 'contenu_ocr', 'metadonnees_metier', 'exercice_id', 'structure_id',
        'created_by', 'updated_by', 'archived_by', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'statut' => DocumentStatut::class,
            'niveau_confidentialite' => NiveauConfidentialite::class,
            'signature_status' => SignatureStatut::class,
            'date_document' => 'date',
            'date_depot' => 'datetime',
            'date_expiration' => 'date',
            'date_archivage' => 'datetime',
            'est_archive' => 'boolean',
            'est_verrouille' => 'boolean',
            'est_signe' => 'boolean',
            'metadonnees_metier' => 'array',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(GedCategorie::class, 'categorie_id');
    }

    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(GedTypeDocument::class, 'type_document_id');
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(GedDossier::class, 'dossier_id');
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(GedVersion::class, 'document_id')->orderByDesc('numero');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(GedSignature::class, 'document_id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(GedValidation::class, 'document_id');
    }

    public function archives(): HasMany
    {
        return $this->hasMany(GedArchive::class, 'document_id');
    }

    public function metadonnees(): HasMany
    {
        return $this->hasMany(GedMetadonnee::class, 'document_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(GedTag::class, 'ged_document_tags', 'document_id', 'tag_id')
            ->withTimestamps();
    }

    public function partages(): HasMany
    {
        return $this->hasMany(GedPartage::class, 'document_id');
    }

    public function acces(): HasMany
    {
        return $this->hasMany(GedAcces::class, 'document_id');
    }

    public function journaux(): HasMany
    {
        return $this->hasMany(GedJournal::class, 'document_id')->orderByDesc('created_at');
    }

    public function commentaires(): HasMany
    {
        return $this->hasMany(GedCommentaire::class, 'document_id');
    }

    public function favoris(): HasMany
    {
        return $this->hasMany(GedFavori::class, 'document_id');
    }

    public function numerisations(): HasMany
    {
        return $this->hasMany(GedNumerisation::class, 'document_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estModifiable(): bool
    {
        return ! $this->est_verrouille && ! $this->est_archive
            && ! in_array($this->statut, [DocumentStatut::Signe, DocumentStatut::Archive], true);
    }

    public function estConsultablePar(User $user): bool
    {
        if ($user->can('ged.admin')) {
            return true;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        return $user->can('ged.voir');
    }
}
