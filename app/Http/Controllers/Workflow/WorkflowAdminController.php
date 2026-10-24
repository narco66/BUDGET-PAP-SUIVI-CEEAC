<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Services\Workflow\WorkflowCircuitAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/** Administration des circuits D14 (P1). */
class WorkflowAdminController extends Controller
{
    public function __construct(private readonly WorkflowCircuitAdminService $circuits) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('workflow.admin'), 403);

        return view('workflow.admin.index', [
            'workflows' => $this->circuits->lister(),
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

        $workflow = $this->circuits->creer($request);

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

        $this->circuits->modifier($request, $workflow);

        return back()->with('succes', 'Circuit enregistré.');
    }

    public function toggle(Workflow $workflow): RedirectResponse
    {
        abort_unless(auth()->user()->can('workflow.update'), 403);

        $workflow = $this->circuits->basculer($workflow);

        return back()->with('succes', $workflow->actif ? 'Circuit activé.' : 'Circuit désactivé.');
    }
}
