<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opération de restauration (workflow contrôlé : demande → validation 4-yeux → exécution tracée).
 * Le système ne restaure jamais automatiquement : l'exécution destructive reste un acte manuel autorisé.
 */
class RestoreOperation extends Model
{
    protected $table = 'restore_operations';

    protected $fillable = [
        'reference', 'backup_id', 'type', 'statut', 'point_restauration', 'motif', 'motif_rejet',
        'demande_par', 'valide_par', 'execute_par', 'valide_le', 'execute_le',
    ];

    protected $casts = ['valide_le' => 'datetime', 'execute_le' => 'datetime'];

    public const TYPES = [
        'complet' => 'Restauration complète', 'partiel' => 'Restauration partielle',
        'base' => 'Base de données', 'documentaire' => 'Documentaire (GED)',
    ];

    public const STATUTS = [
        'demande' => 'Demandée', 'validee' => 'Validée (à exécuter)', 'rejetee' => 'Rejetée', 'executee' => 'Exécutée',
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function executeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'execute_par');
    }

    public static function statutClasse(string $statut): string
    {
        return ['demande' => 'warning', 'validee' => 'info', 'rejetee' => 'dark', 'executee' => 'success'][$statut] ?? 'secondary';
    }
}
