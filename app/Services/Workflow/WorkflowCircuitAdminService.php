<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEtape;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class WorkflowCircuitAdminService
{
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

    public function creer(Request $request): Workflow
    {
        $workflow = Workflow::create($this->valider($request));
        $this->syncEtapes($workflow, $request->input('etapes', []));
        AuditLogger::enregistrer('workflow_circuit_cree', $workflow, null, $workflow->toArray());

        return $workflow->load('etapes')->loadCount('instances');
    }

    public function modifier(Request $request, Workflow $workflow): Workflow
    {
        $avant = $workflow->toArray();
        $workflow->update($this->valider($request, $workflow));
        $this->syncEtapes($workflow, $request->input('etapes', []));
        AuditLogger::enregistrer('workflow_circuit_modifie', $workflow, $avant, $workflow->fresh()->toArray());

        return $workflow->fresh(['etapes'])->loadCount('instances');
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
