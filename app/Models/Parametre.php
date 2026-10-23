<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    use Auditable;

    protected $fillable = ['cle', 'valeur', 'libelle', 'groupe', 'type'];

    /** Récupère une valeur typée. */
    public static function valeur(string $cle, mixed $defaut = null): mixed
    {
        $p = static::firstWhere('cle', $cle);
        if (! $p) {
            return $defaut;
        }

        return match ($p->type) {
            'number' => (float) $p->valeur,
            'bool' => filter_var($p->valeur, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $p->valeur, true),
            default => $p->valeur,
        };
    }
}
