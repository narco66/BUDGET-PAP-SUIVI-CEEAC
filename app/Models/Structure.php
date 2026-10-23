<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Structure extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'code', 'libelle', 'sigle', 'type', 'parent_id', 'structure_officielle_id',
        'niveau', 'ordre', 'responsable_user_id', 'description', 'actif',
    ];

    protected $casts = ['actif' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Structure officielle CEEAC lorsque cet enregistrement est un alias de demonstration. */
    public function structureOfficielle(): BelongsTo
    {
        return $this->belongsTo(self::class, 'structure_officielle_id');
    }

    public function estAliasDemonstration(): bool
    {
        return $this->structure_officielle_id !== null;
    }

    /** Retourne la structure officielle si alias, sinon soi-meme. */
    public function structureEffective(): self
    {
        return $this->structureOfficielle ?? $this;
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordre');
    }

    /** Sous-arbre complet pour l'organigramme. */
    public function enfantsRecursifs(): HasMany
    {
        return $this->enfants()->with('enfantsRecursifs');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(Delegation::class);
    }

    public function interims(): HasMany
    {
        return $this->hasMany(Interim::class);
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }

    public function lignesBudgetaires(): HasMany
    {
        return $this->hasMany(LigneBudgetaire::class);
    }

    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class);
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function ancetres(): array
    {
        $ancetres = [];
        $current = $this->parent;

        while ($current) {
            $ancetres[] = $current;
            $current = $current->parent;
        }

        return $ancetres;
    }

    public function departement(): ?self
    {
        return $this->structureDeType('departement');
    }

    public function direction(): ?self
    {
        return $this->structureDeType('direction');
    }

    public function service(): ?self
    {
        return $this->structureDeType('service');
    }

    private function structureDeType(string $type): ?self
    {
        if ($this->type === $type) {
            return $this;
        }

        foreach ($this->ancetres() as $ancetre) {
            if ($ancetre->type === $type) {
                return $ancetre;
            }
        }

        return null;
    }
}
