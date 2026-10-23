<?php

namespace App\Http\Controllers;

use App\Models\Alerte;
use App\Services\Alertes\AlerteDashboardService;
use App\Services\Alertes\AlerteRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Dashboard alertes institutionnelles (N-P1). */
class AlerteController extends Controller
{
    public function __construct(
        private readonly AlerteDashboardService $dashboard,
        private readonly AlerteRegistryService $registry,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('alertes.view'), 403);

        $module = $request->string('module')->toString() ?: null;
        $statut = $request->string('statut')->toString() ?: null;

        return view('alertes.index', [
            'data' => $this->dashboard->tableau($module, $statut),
            'filtres' => ['module' => $module, 'statut' => $statut],
        ]);
    }

    public function resoudre(Request $request, Alerte $alerte): RedirectResponse
    {
        abort_unless($request->user()->can('alertes.resolve'), 403);
        abort_unless($alerte->estOuverte(), 422, 'Alerte déjà clôturée.');

        $data = $request->validate(['commentaire' => ['nullable', 'string', 'max:2000']]);

        $this->registry->resoudre($alerte, $request->user(), $data['commentaire'] ?? null);

        return back()->with('succes', 'Alerte marquée comme résolue.');
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('alertes.manage'), 403);

        $csv = "reference;module;code;structure_code;structure;criticite;statut;titre;detecte_le;resolu_le\n";
        foreach ($this->dashboard->exportCsvLignes() as $row) {
            $csv .= implode(';', array_map(fn ($v) => str_replace(';', ',', (string) ($v ?? '')), $row))."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="alertes-institutionnelles.csv"',
        ]);
    }
}
