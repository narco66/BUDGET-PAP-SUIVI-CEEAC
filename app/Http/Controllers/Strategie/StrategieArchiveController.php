<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Models\Strategie\Vision;
use App\Services\Strategie\StrategieReferentielService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StrategieArchiveController extends Controller
{
    public function __construct(private readonly StrategieReferentielService $service)
    {
    }

    public function archiver(Request $request, string $entite, int $id): RedirectResponse
    {
        $model = $this->resolve($entite, $id);
        $this->authorize('archive', $model);
        $this->service->archiver($model);

        return back()->with('succes', 'Élément archivé.');
    }

    public function restaurer(Request $request, string $entite, int $id): RedirectResponse
    {
        $model = $this->resolve($entite, $id);
        $this->authorize('update', $model);
        $this->service->restaurer($model);

        return back()->with('succes', 'Élément restauré.');
    }

    private function resolve(string $entite, int $id): \Illuminate\Database\Eloquent\Model
    {
        $map = [
            'visions' => Vision::class,
            'plans' => \App\Models\Strategie\PlanStrategique::class,
            'orientations' => \App\Models\Strategie\OrientationStrategique::class,
            'programmes' => \App\Models\Strategie\ProgrammeStrategique::class,
            'projets' => \App\Models\Strategie\ProjetStrategique::class,
            'portefeuilles' => \App\Models\Strategie\PortefeuilleProjet::class,
            'programmations-pluri' => \App\Models\Strategie\ProgrammationPluriannuelle::class,
            'programmations-annuelles' => \App\Models\Strategie\ProgrammationAnnuelle::class,
            'objectifs' => \App\Models\Strategie\ObjectifStrategique::class,
            'indicateurs' => \App\Models\Strategie\IndicateurStrategique::class,
            'risques' => \App\Models\Strategie\RisqueStrategique::class,
        ];

        abort_unless(isset($map[$entite]), 404);

        return $map[$entite]::findOrFail($id);
    }
}
