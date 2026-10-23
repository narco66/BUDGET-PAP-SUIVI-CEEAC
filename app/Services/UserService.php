<?php

namespace App\Services;

use App\Models\HistoriqueAffectation;
use App\Models\PasswordHistory;
use App\Models\Profil;
use App\Models\User;
use App\Services\Institution\InstitutionGouvernanceService;
use App\Services\Workflow\SodPolicyService;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * Cycle de vie des comptes : création/mise à jour, mots de passe (historique + 1ʳᵉ connexion),
 * affectation des rôles/profils/structures avec gardes de sécurité (moindre privilège,
 * protection des rôles sensibles, anti auto-escalade).
 */
class UserService
{
    public const HISTORIQUE_MDP = 5;

    public function __construct(
        private readonly InstitutionGouvernanceService $gouvernance,
        private readonly SodPolicyService $sod,
    ) {}

    /** Crée un utilisateur ; le mot de passe initial devra être changé à la 1ʳᵉ connexion. */
    public function creer(array $data, User $auteur, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($data, $auteur, $avatar) {
            $motDePasse = $data['password'] ?? null;
            $user = new User($this->champsBase($data));
            $user->password = Hash::make($motDePasse ?? str()->random(16));
            $user->statut = $data['statut'] ?? 'actif';
            $user->actif = $user->statut === 'actif';
            $user->doit_changer_mot_de_passe = true;
            if ($avatar) {
                $user->avatar = $avatar->store('avatars', 'public');
            }
            $user->save();

            $this->enregistrerHistoriqueMdp($user);
            $this->synchroniserRoles($user, $data['roles'] ?? [], $auteur);
            $this->synchroniserProfils($user, $data['profils'] ?? [], $auteur);
            $this->synchroniserStructures($user, $data['structures'] ?? [], $auteur);

            if ($user->structure_id) {
                $this->gouvernance->enregistrerChangementStructure($user, null, $user->structure_id, $auteur);
            }

            AuditLogger::enregistrer('utilisateur_cree', $user, null, ['email' => $user->email]);

            return $user;
        });
    }

    public function mettreAJour(User $user, array $data, User $auteur, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($user, $data, $auteur, $avatar) {
            $avant = $user->only(['name', 'email', 'statut', 'structure_id']);
            $ancienneStructureId = $user->structure_id;
            $user->fill($this->champsBase($data));

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
                $user->doit_changer_mot_de_passe = true;
                $this->enregistrerHistoriqueMdp($user);
            }

            if (array_key_exists('statut', $data)) {
                $user->statut = $data['statut'];
                $user->actif = $data['statut'] === 'actif';
            }

            if ($avatar) {
                $this->supprimerAvatar($user);
                $user->avatar = $avatar->store('avatars', 'public');
            }

            $user->save();

            $this->synchroniserRoles($user, $data['roles'] ?? [], $auteur);
            $this->synchroniserProfils($user, $data['profils'] ?? [], $auteur);
            $this->synchroniserStructures($user, $data['structures'] ?? [], $auteur);

            if ((int) $user->structure_id !== (int) $ancienneStructureId) {
                $this->gouvernance->enregistrerChangementStructure($user, $ancienneStructureId, $user->structure_id, $auteur);
            }

            AuditLogger::enregistrer('utilisateur_modifie', $user, $avant, $user->only(['name', 'email', 'statut', 'structure_id']));

            return $user;
        });
    }

    public function changerStatut(User $user, string $statut): void
    {
        $user->update(['statut' => $statut, 'actif' => $statut === 'actif']);
        AuditLogger::enregistrer('utilisateur_statut_'.$statut, $user);
    }

    /** Réinitialise le mot de passe et force le changement à la prochaine connexion. */
    public function reinitialiserMotDePasse(User $user, string $nouveau): void
    {
        $user->update([
            'password' => Hash::make($nouveau),
            'doit_changer_mot_de_passe' => true,
        ]);
        $this->enregistrerHistoriqueMdp($user);
        AuditLogger::enregistrer('mot_de_passe_reinitialise', $user);
    }

    /** Changement par l'utilisateur lui-même (1ʳᵉ connexion ou volontaire). */
    public function definirMotDePasse(User $user, string $nouveau): void
    {
        $user->update([
            'password' => Hash::make($nouveau),
            'doit_changer_mot_de_passe' => false,
        ]);
        $this->enregistrerHistoriqueMdp($user);
        AuditLogger::enregistrer('mot_de_passe_change', $user);
    }

    /** Le mot de passe figure-t-il dans les N derniers utilisés ? */
    public function motDePasseReutilise(User $user, string $plain): bool
    {
        return $user->passwordHistories()->limit(self::HISTORIQUE_MDP)->get()
            ->contains(fn (PasswordHistory $h) => Hash::check($plain, $h->password));
    }

    // --- Affectations sécurisées ---

    /**
     * Synchronise les rôles avec gardes :
     * - seul un administrateur peut attribuer un rôle « protégé » (dont administrateur) ;
     * - nul ne peut modifier ses PROPRES rôles protégés (anti auto-escalade).
     */
    public function synchroniserRoles(User $user, array $rolesDemandes, User $auteur): void
    {
        $protegees = Role::where('protege', true)->pluck('name')->all();
        $actuels = $user->getRoleNames()->all();
        $autorises = [];

        foreach ($rolesDemandes as $role) {
            $estProtege = in_array($role, $protegees, true);
            if ($estProtege && ! $auteur->estAdministrateur()) {
                continue; // moindre privilège : refus silencieux
            }
            if ($estProtege && $auteur->id === $user->id && ! in_array($role, $actuels, true)) {
                continue; // anti auto-escalade
            }
            $autorises[] = $role;
        }

        // conserve les rôles protégés déjà détenus si l'auteur n'a pas le droit de les retirer
        if (! $auteur->estAdministrateur()) {
            foreach (array_intersect($actuels, $protegees) as $conserve) {
                $autorises[] = $conserve;
            }
        }

        $rolesFinaux = array_values(array_unique($autorises));
        $this->sod->assertRolesCompatibles($rolesFinaux);
        $user->syncRoles($rolesFinaux);
    }

    /** Affecte des profils ; les rôles des profils sont fusionnés dans les rôles de l'utilisateur. */
    public function synchroniserProfils(User $user, array $profilIds, User $auteur): void
    {
        $user->profils()->sync($profilIds);

        if ($profilIds) {
            $rolesProfils = Profil::with('roles')->whereIn('id', $profilIds)
                ->get()->flatMap->roles->pluck('name')->all();
            $this->synchroniserRoles($user, array_unique(array_merge($user->getRoleNames()->all(), $rolesProfils)), $auteur);
        }
    }

    public function synchroniserStructures(User $user, array $structureIds, ?User $modifiePar = null): void
    {
        $principale = $user->structure_id;
        $pivot = [];
        foreach ($structureIds as $id) {
            $pivot[$id] = ['principal' => (int) $id === (int) $principale];
        }
        if ($principale && ! isset($pivot[$principale])) {
            $pivot[$principale] = ['principal' => true];
        }
        $user->structures()->sync($pivot);
    }

    // --- Internes ---

    private function champsBase(array $data): array
    {
        return collect($data)->only([
            'matricule', 'name', 'email', 'telephone', 'structure_id', 'fonction_id', 'poste_id',
        ])->all();
    }

    private function enregistrerHistoriqueMdp(User $user): void
    {
        $user->passwordHistories()->create(['password' => $user->password, 'created_at' => now()]);
    }

    private function supprimerAvatar(User $user): void
    {
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
    }
}
