<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowEtape;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/** Administration des circuits D14 (P1). */
class WorkflowAdminController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->can('workflow.admin'), 403);

        return view('workflow.admin.index', [
            'workflows' => Workflow::withCount('instances')->with('etapes')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('workflow.create'), 403);

        return view('workflow.admin.form', [
            'workflow' => new Workflow(['actif' => true]),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('workflow.create'), 403);

        $data = $this->validerWorkflow($request);
        $workflow = Workflow::create($data);
        $this->syncEtapes($workflow, $request->input('etapes', []));
        AuditLogger::enregistrer('workflow_circuit_cree', $workflow, null, $workflow->toArray());

        return redirect()->route('admin.workflows.edit', $workflow)->with('succes', 'Circuit créé.');
    }

    public function edit(Workflow $workflow): View
    {
        abort_unless(auth()->user()->can('workflow.update'), 403);

        $workflow->load('etapes');

        return view('workflow.admin.form', [
            'workflow' => $workflow,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        abort_unless(auth()->user()->can('workflow.update'), 403);

        $avant = $workflow->toArray();
        $workflow->update($this->validerWorkflow($request, $workflow));
        $this->syncEtapes($workflow, $request->input('etapes', []));
        AuditLogger::enregistrer('workflow_circuit_modifie', $workflow, $avant, $workflow->fresh()->toArray());

        return back()->with('succes', 'Circuit enregistré.');
    }

    public function toggle(Workflow $workflow): RedirectResponse
    {
        abort_unless(auth()->user()->can('workflow.update'), 403);

        $workflow->update(['actif' => ! $workflow->actif]);

        return back()->with('succes', $workflow->actif ? 'Circuit activé.' : 'Circuit désactivé.');
    }

    /** @return array<string, mixed> */
    private function validerWorkflow(Request $request, ?Workflow $workflow = null): array
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

    /** @param list<array<string,mixed>> $etapes */
    private function syncEtapes(Workflow $workflow, array $etapes): void
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
