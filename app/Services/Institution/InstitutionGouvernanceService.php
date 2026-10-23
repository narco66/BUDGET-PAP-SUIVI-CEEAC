<?php

namespace App\Services\Institution;

use App\Models\Delegation;
use App\Models\HistoriqueAffectation;
use App\Models\Interim;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Délégations, intérims et historique des affectations (phase A6). */
class InstitutionGouvernanceService
{
    public function enregistrerChangementStructure(
        User $user,
        ?int $ancienneStructureId,
        ?int $nouvelleStructureId,
        ?User $modifiePar = null,
    ): void {
        if ($ancienneStructureId === $nouvelleStructureId) {
            return;
        }

        DB::transaction(function () use ($user, $ancienneStructureId, $nouvelleStructureId, $modifiePar) {
            $this->cloreAffectationsOuvertes($user);

            if ($ancienneStructureId && $ancienneStructureId !== $nouvelleStructureId) {
                $existe = HistoriqueAffectation::query()
                    ->where('user_id', $user->id)
                    ->where('structure_id', $ancienneStructureId)
                    ->exists();

                if (! $existe) {
                    HistoriqueAffectation::create([
                        'user_id' => $user->id,
                        'structure_id' => $ancienneStructureId,
                        'date_debut' => $user->created_at?->toDateString() ?? now()->toDateString(),
                        'date_fin' => now()->toDateString(),
                        'principal' => true,
                        'modifie_par' => $modifiePar?->id,
                    ]);
                }
            }

            if ($nouvelleStructureId) {
                HistoriqueAffectation::create([
                    'user_id' => $user->id,
                    'structure_id' => $nouvelleStructureId,
                    'date_debut' => now()->toDateString(),
                    'principal' => true,
                    'modifie_par' => $modifiePar?->id,
                ]);
            }
        });
    }

    public function cloreAffectationsOuvertes(User $user, ?string $dateFin = null): void
    {
        HistoriqueAffectation::query()
            ->where('user_id', $user->id)
            ->whereNull('date_fin')
            ->update(['date_fin' => $dateFin ?? now()->toDateString()]);
    }

    /** @param  array{structure_id:int,delegue_par:int,delegue_a:int,date_debut:string,date_fin?:?string,motif?:?string}  $data */
    public function creerDelegation(array $data): Delegation
    {
        $this->validerDelegation($data);

        return Delegation::create([
            ...$data,
            'actif' => true,
        ]);
    }

    /** @param  array{structure_id:int,titulaire_user_id:int,interim_user_id:int,date_debut:string,date_fin?:?string,motif?:?string,cree_par?:?int}  $data */
    public function creerInterim(array $data): Interim
    {
        $this->validerInterim($data);

        return Interim::create([
            ...$data,
            'actif' => true,
        ]);
    }

    public function cloturerDelegation(Delegation $delegation, ?string $dateFin = null): void
    {
        $delegation->update([
            'actif' => false,
            'date_fin' => $dateFin ?? now()->toDateString(),
        ]);
    }

    public function cloturerInterim(Interim $interim, ?string $dateFin = null): void
    {
        $interim->update([
            'actif' => false,
            'date_fin' => $dateFin ?? now()->toDateString(),
        ]);
    }

    /** @return Collection<int, Delegation> */
    public function delegationsActivesPour(User $user): Collection
    {
        return Delegation::query()
            ->with('structure', 'deleguant', 'delegue')
            ->enCours()
            ->where(fn ($q) => $q->where('delegue_a', $user->id)->orWhere('delegue_par', $user->id))
            ->orderByDesc('date_debut')
            ->get();
    }

    /** @return Collection<int, Interim> */
    public function interimsActifsPour(User $user): Collection
    {
        return Interim::query()
            ->with('structure', 'titulaire', 'interim')
            ->enCours()
            ->where(fn ($q) => $q->where('interim_user_id', $user->id)->orWhere('titulaire_user_id', $user->id))
            ->orderByDesc('date_debut')
            ->get();
    }

    public function utilisateurEstDelegueSurStructure(User $user, int $structureId): bool
    {
        return Delegation::query()
            ->enCours()
            ->where('structure_id', $structureId)
            ->where('delegue_a', $user->id)
            ->exists();
    }

    public function utilisateurEstInterimSurStructure(User $user, int $structureId): bool
    {
        return Interim::query()
            ->enCours()
            ->where('structure_id', $structureId)
            ->where('interim_user_id', $user->id)
            ->exists();
    }

    /** Responsable titulaire, délégataire ou intérimaire habilité sur la structure. */
    public function utilisateurSubstitueResponsable(User $user, User $responsable, int $structureId): bool
    {
        if ($user->id === $responsable->id) {
            return true;
        }

        return Delegation::query()
            ->enCours()
            ->where('structure_id', $structureId)
            ->where('delegue_a', $user->id)
            ->where('delegue_par', $responsable->id)
            ->exists()
            || Interim::query()
                ->enCours()
                ->where('structure_id', $structureId)
                ->where('interim_user_id', $user->id)
                ->where('titulaire_user_id', $responsable->id)
                ->exists();
    }

    /** @return list<string> */
    public function listerAnomalies(): array
    {
        $anomalies = [];

        Delegation::query()->where('actif', true)->each(function (Delegation $d) use (&$anomalies) {
            if ($d->delegue_par === $d->delegue_a) {
                $anomalies[] = "Delegation #{$d->id} — deleguant et delegue identiques";
            }
            if ($d->date_fin && $d->date_fin->lt($d->date_debut)) {
                $anomalies[] = "Delegation #{$d->id} — date fin anterieure a la date debut";
            }
        });

        Interim::query()->where('actif', true)->each(function (Interim $i) use (&$anomalies) {
            if ($i->titulaire_user_id === $i->interim_user_id) {
                $anomalies[] = "Interim #{$i->id} — titulaire et interim identiques";
            }
            if ($i->date_fin && $i->date_fin->lt($i->date_debut)) {
                $anomalies[] = "Interim #{$i->id} — date fin anterieure a la date debut";
            }
        });

        HistoriqueAffectation::query()
            ->select('user_id')
            ->whereNull('date_fin')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id')
            ->each(function ($userId) use (&$anomalies) {
                $anomalies[] = "Utilisateur #{$userId} — plusieurs affectations ouvertes";
            });

        return $anomalies;
    }

    /** @param  array<string, mixed>  $data */
    private function validerDelegation(array $data): void
    {
        if ((int) $data['delegue_par'] === (int) $data['delegue_a']) {
            throw new InvalidArgumentException('Le deleguant et le delegue doivent etre distincts.');
        }

        if (! empty($data['date_fin']) && $data['date_fin'] < $data['date_debut']) {
            throw new InvalidArgumentException('La date de fin doit etre posterieure a la date de debut.');
        }
    }

    /** @param  array<string, mixed>  $data */
    private function validerInterim(array $data): void
    {
        if ((int) $data['titulaire_user_id'] === (int) $data['interim_user_id']) {
            throw new InvalidArgumentException('Le titulaire et l\'interim doivent etre distincts.');
        }

        if (! empty($data['date_fin']) && $data['date_fin'] < $data['date_debut']) {
            throw new InvalidArgumentException('La date de fin doit etre posterieure a la date de debut.');
        }
    }
}
