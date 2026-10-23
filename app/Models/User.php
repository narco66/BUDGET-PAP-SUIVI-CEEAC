<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'matricule', 'name', 'email', 'password', 'structure_id', 'fonction_id', 'poste_id',
    'telephone', 'preferences_notifications', 'avatar', 'actif', 'statut', 'doit_changer_mot_de_passe', 'derniere_connexion_at',
    'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, Auditable, HasApiTokens, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
            'derniere_connexion_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'preferences_notifications' => 'array',
        ];
    }

    // --- Relations ---

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function fonction(): BelongsTo
    {
        return $this->belongsTo(Fonction::class);
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    /** Rattachements multiples (la structure principale = users.structure_id). */
    public function structures(): BelongsToMany
    {
        return $this->belongsToMany(Structure::class, 'user_structures')
            ->withPivot('principal')->withTimestamps();
    }

    public function profils(): BelongsToMany
    {
        return $this->belongsToMany(Profil::class, 'user_profils')->withTimestamps();
    }

    public function accessScopes(): HasMany
    {
        return $this->hasMany(UserAccessScope::class);
    }

    public function historiquesAffectations(): HasMany
    {
        return $this->hasMany(HistoriqueAffectation::class, 'user_id')->orderByDesc('date_debut');
    }

    public function delegationsRecues(): HasMany
    {
        return $this->hasMany(Delegation::class, 'delegue_a');
    }

    public function delegationsAccordees(): HasMany
    {
        return $this->hasMany(Delegation::class, 'delegue_par');
    }

    public function interimsAssures(): HasMany
    {
        return $this->hasMany(Interim::class, 'interim_user_id');
    }

    public function interimsTitulaire(): HasMany
    {
        return $this->hasMany(Interim::class, 'titulaire_user_id');
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class)->latest('created_at');
    }

    // --- Helpers ---

    public function estActif(): bool
    {
        return $this->actif && $this->statut === 'actif';
    }

    public function estAdministrateur(): bool
    {
        return $this->hasRole('administrateur');
    }

    public function scopeActifs($query)
    {
        return $query->where('actif', true)->where('statut', 'actif');
    }
}
