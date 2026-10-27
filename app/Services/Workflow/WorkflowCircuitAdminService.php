<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEtape;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class WorkflowCircuitAdminService
{
    public function __construct(private readonly WorkflowVersionService $versions) {}

    /** @return Collection<int, Workflow> */
    public function lister(): Collection
    {
        return Workflow::query()
            ->withCount('instances')
            ->with('etapes')
            ->orderBy('code')
            ->get();
    }

    public function trouver(int $id): Workflow
    {
        return Workflow::with(['etapes'])->withCount('instances')->findOrFail($id);
    }

    /** @return array<string, mixed> */
    public function options(?User $user = null): array
    {
        $user ??= auth()->user();

        return [
            'roles' => Role::query()->orderBy('name')->pluck('name')->values()->all(),
            'peut_creer' => (bool) $user?->can('workflow.create'),
            'peut_modifier' => (bool) $user?->can('workflow.update'),
        ];
    }

    /**
     * Création ou modification = publication d'une nouvelle version (référentiel § 7) : la définition est d'abord
     * validée (§ 7.2), puis enregistrée et figée en version ; les dossiers en cours restent sur leur version.
     */
    public function creer(Request $request): Workflow
    {
        $attributs = $this->valider($request);
        $etapes = $this->validerEtapes($request->input('etapes', []));

        return DB::transaction(function () use ($attributs, $etapes, $request) {
            $workflow = Workflow::create($attributs);
            $this->syncEtapes($workflow, $etapes);
            AuditLogger::enregistrer('workflow_circuit_cree', $workflow, null, $workflow->toArray());
            $this->versions->publier($workflow->fresh(), $request->user(), 'Version initiale');

            return $workflow->load('etapes')->loadCount('instances');
        });
    }

    public function modifier(Request $request, Workflow $workflow): Workflow
    {
        $attributs = $this->valider($request, $workflow);
        $etapes = $this->validerEtapes($request->input('etapes', []), $workflow);

        return DB::transaction(function () use ($attributs, $etapes, $request, $workflow) {
            $avant = $workflow->toArray();
            $workflow->update($attributs);
            $this->syncEtapes($workflow, $etapes);
            AuditLogger::enregistrer('workflow_circuit_modifie', $workflow, $avant, $workflow->fresh()->toArray());
            $this->versions->publier($workflow->fresh(), $request->user());

            return $workflow->fresh(['etapes'])->loadCount('instances');
        });
    }

    /**
     * Validations bloquantes avant publication (§ 7.2) et conservation de l'historique (P07) :
     * au moins une étape, ordres uniques et positifs, rôle connu, délai positif, et aucune étape
     * déjà utilisée par des dossiers ne peut disparaître (ses décisions passées y sont rattachées).
     *
     * @param  list<array<string, mixed>>  $etapes
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function validerEtapes(array $etapes, ?Workflow $workflow = null): array
    {
        $etapes = array_values(array_filter($etapes, fn ($e) => is_array($e) && ! (blank($e['role'] ?? null) && blank($e['libelle'] ?? null))));
        $erreurs = [];
        if ($etapes === []) {
            $erreurs[] = 'Le circuit doit comporter au moins une étape.';
        }
        $roles = Role::query()->pluck('name')->all();
        $ordres = [];
        foreach ($etapes as $i => $e) {
            $n = $i + 1;
            $ordre = (int) ($e['ordre'] ?? $n);
            if ($ordre < 1) {
                $erreurs[] = "Étape {$n} : l'ordre doit être un entier positif.";
            }
            if (in_array($ordre, $ordres, true)) {
                $erreurs[] = "Étape {$n} : l'ordre {$ordre} est déjà utilisé (étape inaccessible ou double traitement).";
            }
            $ordres[] = $ordre;
            if (blank($e['libelle'] ?? null)) {
                $erreurs[] = "Étape {$n} : le libellé est obligatoire.";
            }
            if (! in_array($e['role'] ?? null, $roles, true)) {
                $erreurs[] = "Étape {$n} : le rôle « ".($e['role'] ?? '')." » n'existe pas.";
            }
            if (isset($e['delai_jours']) && $e['delai_jours'] !== '' && (int) $e['delai_jours'] < 0) {
                $erreurs[] = "Étape {$n} : le délai ne peut pas être négatif.";
            }
        }
        if ($workflow) {
            $conservees = collect($etapes)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $retirees = $workflow->etapes()->whereNotIn('id', $conservees)->get();
            foreach ($retirees as $etape) {
                $usages = \App\Models\WorkflowInstanceEtape::query()->where('workflow_etape_id', $etape->id)->count();
                if ($usages > 0) {
                    $erreurs[] = "L'étape « {$etape->libelle} » est rattachée à {$usages} décision(s) de dossiers : elle ne peut pas être retirée. Désactivez le circuit et créez-en un nouveau si la procédure change.";
                }
            }
            if ($workflow->transitions()->exists()) {
                $inconnues = $workflow->transitions()->get()->filter(fn ($t) => ! in_array((int) $t->etape_source_ordre, $ordres, true)
                    || ($t->etape_cible_ordre !== null && ! in_array((int) $t->etape_cible_ordre, $ordres, true)));
                if ($inconnues->isNotEmpty()) {
                    $erreurs[] = 'Des transitions conditionnelles visent une étape absente du circuit : publication impossible.';
                }
            }
        }
        if ($erreurs) {
            throw ValidationException::withMessages(['etapes' => $erreurs]);
        }

        return $etapes;
    }

    public function basculer(Workflow $workflow): Workflow
    {
        $workflow->update(['actif' => ! $workflow->actif]);

        return $workflow->fresh(['etapes'])->loadCount('instances');
    }

    /** @return array<string, mixed> */
    public function serialiser(Workflow $workflow, bool $detail = false): array
    {
        $data = [
            'id' => $workflow->id,
            'code' => $workflow->code,
            'libelle' => $workflow->libelle,
            'type_objet' => $workflow->type_objet,
            'description' => $workflow->description,
            'actif' => (bool) $workflow->actif,
            'version' => (int) ($workflow->version ?? 1),
            'instances_count' => (int) ($workflow->instances_count ?? $workflow->instances()->count()),
            'etapes_count' => $workflow->relationLoaded('etapes')
                ? $workflow->etapes->count()
                : $workflow->etapes()->count(),
        ];

        if ($detail) {
            $data['etapes'] = $workflow->etapes->map(fn (WorkflowEtape $e) => [
                'id' => $e->id,
                'ordre' => $e->ordre,
                'libelle' => $e->libelle,
                'role' => $e->role,
                'delai_jours' => $e->delai_jours,
            ])->values()->all();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function valider(Request $request, ?Workflow $workflow = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                Rule::unique('workflows', 'code')->ignore($workflow?->id),
            ],
            'libelle' => ['required', 'string', 'max:255'],
            'type_objet' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'actif' => ['nullable', 'boolean'],
        ]) + ['actif' => $request->boolean('actif')];
    }

    /** @param list<array<string, mixed>> $etapes */
    public function syncEtapes(Workflow $workflow, array $etapes): void
    {
        $ids = [];
        foreach ($etapes as $i => $row) {
            if (empty($row['role']) || empty($row['libelle'])) {
                continue;
            }
            $ordre = (int) ($row['ordre'] ?? ($i + 1));
            $etape = isset($row['id'])
                ? WorkflowEtape::where('workflow_id', $workflow->id)->find($row['id'])
                : null;

            $payload = [
                'ordre' => $ordre,
                'libelle' => $row['libelle'],
                'role' => $row['role'],
                'delai_jours' => isset($row['delai_jours']) && $row['delai_jours'] !== ''
                    ? (int) $row['delai_jours']
                    : null,
            ];

            if ($etape) {
                $etape->update($payload);
                $ids[] = $etape->id;
            } else {
                $ids[] = $workflow->etapes()->create($payload)->id;
            }
        }

        $workflow->etapes()->whereNotIn('id', $ids)->delete();
    }
}
