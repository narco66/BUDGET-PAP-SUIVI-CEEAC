<?php

namespace App\Http\Controllers;

use App\Models\ControleInterne;
use App\Models\Structure;
use App\Models\User;
use App\Support\Numero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Dispositif de contrôle interne (référentiel COSO — 5 composantes) + évaluation d'efficacité.
 * Permissions `controle_interne.*`.
 */
class ControleInterneController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('controle_interne.voir'), 403);

        $controles = ControleInterne::with(['structure', 'responsable'])
            ->when($request->filled('composante'), fn ($q) => $q->where('composante', $request->composante))
            ->when($request->filled('evaluation'), fn ($q) => $q->where('evaluation', $request->evaluation))
            ->orderBy('composante')->orderBy('code')->paginate(20)->withQueryString();

        // Cartographie COSO : par composante, total + répartition par évaluation.
        $parComposante = [];
        foreach (array_keys(ControleInterne::COMPOSANTES) as $c) {
            $parComposante[$c] = [
                'total' => ControleInterne::where('composante', $c)->count(),
                'efficace' => ControleInterne::where('composante', $c)->where('evaluation', 'efficace')->count(),
                'partiel' => ControleInterne::where('composante', $c)->where('evaluation', 'partiel')->count(),
                'inefficace' => ControleInterne::where('composante', $c)->where('evaluation', 'inefficace')->count(),
                'non_evalue' => ControleInterne::where('composante', $c)->where('evaluation', 'non_evalue')->count(),
            ];
        }

        return view('controle-interne.index', [
            'controles' => $controles,
            'composantes' => ControleInterne::COMPOSANTES,
            'evaluations' => ControleInterne::EVALUATIONS,
            'parComposante' => $parComposante,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('controle_interne.creer'), 403);

        return view('controle-interne.form', $this->options(new ControleInterne(['type' => 'preventif', 'frequence' => 'permanent', 'evaluation' => 'non_evalue', 'actif' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('controle_interne.creer'), 403);
        $controle = ControleInterne::create($this->valider($request) + [
            'code' => Numero::suivant('controles_internes', 'CI', now()->year, 'code'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('controle-interne.index')->with('succes', "Contrôle {$controle->code} enregistré.");
    }

    public function edit(Request $request, ControleInterne $controle): View
    {
        abort_unless($request->user()->can('controle_interne.modifier'), 403);

        return view('controle-interne.form', $this->options($controle));
    }

    public function update(Request $request, ControleInterne $controle): RedirectResponse
    {
        abort_unless($request->user()->can('controle_interne.modifier'), 403);
        $controle->update($this->valider($request));

        return redirect()->route('controle-interne.index')->with('succes', "Contrôle {$controle->code} mis à jour.");
    }

    /** Évaluation de l'efficacité du contrôle. */
    public function evaluer(Request $request, ControleInterne $controle): RedirectResponse
    {
        abort_unless($request->user()->can('controle_interne.modifier'), 403);
        $data = $request->validate(['evaluation' => ['required', Rule::in(array_keys(ControleInterne::EVALUATIONS))]]);
        $controle->update($data + ['date_evaluation' => now()]);

        return back()->with('succes', "Contrôle {$controle->code} évalué : ".ControleInterne::EVALUATIONS[$data['evaluation']].'.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'composante' => ['required', Rule::in(array_keys(ControleInterne::COMPOSANTES))],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'processus' => ['nullable', 'string', 'max:150'],
            'structure_id' => ['nullable', Rule::exists('structures', 'id')],
            'responsable_user_id' => ['nullable', Rule::exists('users', 'id')],
            'type' => ['required', Rule::in(array_keys(ControleInterne::TYPES))],
            'frequence' => ['required', Rule::in(array_keys(ControleInterne::FREQUENCES))],
            'evaluation' => ['required', Rule::in(array_keys(ControleInterne::EVALUATIONS))],
            'actif' => ['nullable', 'boolean'],
        ]);
    }

    private function options(ControleInterne $controle): array
    {
        return [
            'controle' => $controle,
            'composantes' => ControleInterne::COMPOSANTES,
            'types' => ControleInterne::TYPES,
            'frequences' => ControleInterne::FREQUENCES,
            'evaluations' => ControleInterne::EVALUATIONS,
            'structures' => Structure::orderBy('code')->get(['id', 'code', 'libelle', 'sigle']),
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
        ];
    }
}
