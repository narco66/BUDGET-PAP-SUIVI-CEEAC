<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Exécution de sauvegarde (registre + historique). */
class Backup extends Model
{
    protected $fillable = [
        'reference', 'type', 'declenchement', 'chemin', 'taille_octets', 'checksum',
        'statut', 'duree_ms', 'erreur', 'demarre_le', 'termine_le', 'declenche_par',
    ];

    protected $casts = ['demarre_le' => 'datetime', 'termine_le' => 'datetime'];

    public const TYPES = ['base' => 'Base de données', 'fichiers' => 'Fichiers / GED', 'complet' => 'Complète'];
    public const STATUTS = ['en_cours' => 'En cours', 'succes' => 'Succès', 'echec' => 'Échec'];

    public function declencheur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declenche_par');
    }

    public function tailleLisible(): string
    {
        $o = (int) $this->taille_octets;

        return match (true) {
            $o >= 1073741824 => round($o / 1073741824, 2).' Go',
            $o >= 1048576 => round($o / 1048576, 2).' Mo',
            $o >= 1024 => round($o / 1024, 2).' Ko',
            default => $o.' o',
        };
    }
}
