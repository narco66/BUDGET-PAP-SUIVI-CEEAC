<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Services\Workflow\WorkflowCorbeilleService;
use Illuminate\View\View;

/** Corbeille transverse des validations en attente (P1). */
class WorkflowCorbeilleController extends Controller
{
    public function __construct(
        private readonly WorkflowCorbeilleService $corbeille,
    ) {
    }

    public function index(): View
    {
        abort_unless(auth()->user()->can('workflow.validate'), 403);

        return view('workflow.validations.index', [
            'taches' => $this->corbeille->tachesPour(auth()->user()),
        ]);
    }
}
