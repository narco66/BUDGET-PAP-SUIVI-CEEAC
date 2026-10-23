<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Risque institutionnel (registre central — ISO 31000). Criticité = probabilité × impact (échelles 1→4),
 * classée Faible / Modéré / Élevé / Critique. Le module agrège aussi les risques stratégiques et PAP
 * existants (lecture seule) dans la cartographie.
 */
class Risque extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'code', 'categorie_risque_id', 'structure_id', 'exercice_id', 'proprietaire_user_id',
        'libelle', 'description', 'processus', 'probabilite', 'impact', 'mesures_maitrise',
        'statut', 'created_by',
    ];

    protected $casts = ['probabilite' => 'integer', 'impact' => 'integer'];

    /** Échelles (1→4) et statuts. */
    public const PROBABILITES = [1 => 'Rare', 2 => 'Peu probable', 3 => 'Probable', 4 => 'Très probable'];
    public const IMPACTS = [1 => 'Faible', 2 => 'Modéré', 3 => 'Élevé', 4 => 'Critique'];
    public const STATUTS = [
        'identifie' => 'Identifié', 'en_traitement' => 'En traitement',
        'maitrise' => 'Maîtrisé', 'cloture' => 'Clôturé',
    ];

    /** Catégories normées (ISO 31000 / COSO). */
    public const CATEGORIES = [
        'STRAT' => 'Risque stratégique', 'FIN' => 'Risque financier', 'BUDG' => 'Risque budgétaire',
        'OPER' => 'Risque opérationnel', 'INFO' => 'Risque informatique', 'JURI' => 'Risque juridique',
        'CONF' => 'Risque de conformité', 'FRAU' => 'Risque de fraude',
    ];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieRisque::class, 'categorie_risque_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_user_id');
    }

    /** Criticité = probabilité × impact (1 → 16). */
    protected function criticite(): Attribute
    {
        return Attribute::make(get: fn () => (int) $this->probabilite * (int) $this->impact);
    }

    /** Niveau de criticité : Faible ≤3, Modéré 4-7, Élevé 8-11, Critique ≥12. */
    protected function niveau(): Attribute
    {
        return Attribute::make(get: fn () => self::niveauPour($this->criticite));
    }

    public static function niveauPour(int $criticite): string
    {
        return match (true) {
            $criticite >= 12 => 'critique',
            $criticite >= 8 => 'eleve',
            $criticite >= 4 => 'modere',
            default => 'faible',
        };
    }

    /** @return array{cle:string,libelle:string,classe:string} */
    public static function niveauMeta(string $niveau): array
    {
        return [
            'critique' => ['cle' => 'critique', 'libelle' => 'Critique', 'classe' => 'danger'],
            'eleve' => ['cle' => 'eleve', 'libelle' => 'Élevé', 'classe' => 'warning'],
            'modere' => ['cle' => 'modere', 'libelle' => 'Modéré', 'classe' => 'info'],
            'faible' => ['cle' => 'faible', 'libelle' => 'Faible', 'classe' => 'success'],
        ][$niveau] ?? ['cle' => $niveau, 'libelle' => ucfirst($niveau), 'classe' => 'secondary'];
    }

    public function scopeActif($q)
    {
        return $q->whereNull('deleted_at');
    }
}
