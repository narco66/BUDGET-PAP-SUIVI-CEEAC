<?php

namespace App\Models;

use App\Models\Ged\GedDocument;
use App\Services\Ged\GedArchiveService;
use App\Services\Ged\GedIntegrationAutoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;
use Throwable;

class PieceJustificative extends Model
{
    protected $table = 'pieces_justificatives';

    protected $fillable = [
        'attachable_type', 'attachable_id', 'nom', 'chemin',
        'type_mime', 'taille', 'categorie', 'televerse_par', 'ged_document_id',
    ];

    protected static function booted(): void
    {
        // Tout document joint ou généré sur un dossier est intégré automatiquement à la GED.
        static::created(function (PieceJustificative $piece) {
            try {
                app(GedIntegrationAutoService::class)->indexerPiece($piece);
            } catch (Throwable $e) {
                // L'échec d'indexation ne bloque pas le dépôt ; la commande ged:integrer-existants le rattrape.
                Log::warning('Intégration GED de la pièce échouée', ['piece' => $piece->id, 'message' => $e->getMessage()]);
            }
        });

        // Une pièce retirée d'un dossier n'efface pas le document GED : il est archivé avec le motif (traçabilité).
        static::deleted(function (PieceJustificative $piece) {
            $document = $piece->ged_document_id ? GedDocument::find($piece->ged_document_id) : null;
            $user = auth()->user();
            if ($document && ! $document->est_archive && $user) {
                try {
                    app(GedArchiveService::class)->archiver($document, $user, 'Pièce retirée du dossier', 'Retrait de « '.$piece->nom.' »', 'automatique');
                } catch (Throwable $e) {
                    Log::warning('Archivage GED de la pièce retirée échoué', ['piece' => $piece->id, 'message' => $e->getMessage()]);
                }
            }
        });
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'televerse_par');
    }

    public function gedDocument(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class);
    }
}
