<?php

namespace App\Http\Controllers;

use App\Models\ControleInterne;
use App\Models\MissionAudit;
use App\Models\NonConformite;
use App\Models\Structure;
use App\Models\User;
use App\Support\Numero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Non-conformités (écarts) et plans de remédiation. Détection → suivi → résolution.
 * Permissions `conformite.*`.
 */
class NonConformiteController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('conformite.voir'), 403);

        $nc = NonConformite::with(['structure', 'responsable', 'controleInterne'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('gravite'), fn ($q) => $q->where('gravite', $request->gravite))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        $total = NonConformite::count();
        $resolues = NonConformite::whereIn('statut', ['resolue', 'cloturee'])->count();

        return view('conformite.index', [
            'nonConformites' => $nc,
            'sources' => NonConformite::SOURCES,
            'gravites' => NonConformite::GRAVITES,
            'statuts' => NonConformite::STATUTS,
            'synthese' => [
                'total' => $total,
                'ouvertes' => NonConformite::ouverte()->count(),
                'resolues' => $resolues,
                'taux_conformite' => $total > 0 ? round($resolues / $total * 100, 1) : 100.0,
                'critiques' => NonConformite::where('gravite', 'critique')->ouverte()->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('conformite.creer'), 403);

        return view('conformite.form', $this->options(new NonConformite(['source' => 'controle_interne', 'gravite' => 'moyenne', 'statut' => 'ouverte'])));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('conformite.creer'), 403);
        $nc = NonConformite::create($this->valider($request) + [
            'code' => Numero::suivant('non_conformites', 'NC', now()->year, 'code'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('conformite.show', $nc)->with('succes', "Non-conformité {$nc->code} enregistrée.");
    }

    public function show(Request $request, NonConformite $nonConformite): View
    {
        abort_unless($request->user()->can('conformite.voir'), 403);
        $nonConformite->load(['structure', 'responsable', 'controleInterne']);

        return view('conformite.show', [
            'nc' => $nonConformite,
            'statuts' => NonConformite::STATUTS,
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, NonConformite $nonConformite): View
    {
        abort_unless($request->user()->can('conformite.modifier'), 403);

        return view('conformite.form', $this->options($nonConformite));
    }

    public function update(Request $request, NonConformite $nonConformite): RedirectResponse
    {
        abort_unless($request->user()->can('conformite.modifier'), 403);
        $nonConformite->update($this->valider($request));

        return redirect()->route('conformite.show', $nonConformite)->with('succes', "Non-conformité {$nonConformite->code} mise à jour.");
    }

    /** Suivi du plan de remédiation : statut + taux d'avancement. */
    public function suivi(Request $request, NonConformite $nonConformite): RedirectResponse
    {
        abort_unless($request->user()->can('conformite.modifier'), 403);
        $nonConformite->update($request->validate([
            'statut' => ['required', Rule::in(array_keys(NonConformite::STATUTS))],
            'taux_avancement' => ['required', 'integer', 'between:0,100'],
            'responsable_user_id' => ['nullable', Rule::exists('users', 'id')],
            'echeance' => ['nullable', 'date'],
        ]));

        return back()->with('succes', "Remédiation de {$nonConformite->code} mise à jour.");
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'source' => ['required', Rule::in(array_keys(NonConformite::SOURCES))],
            'controle_interne_id' => ['nullable', Rule::exists('controles_internes', 'id')],
            'mission_audit_id' => ['nullable', Rule::exists('missions_audit', 'id')],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'gravite' => ['required', Rule::in(array_keys(NonConformite::GRAVITES))],
            'processus' => ['nullable', 'string', 'max:150'],
            'structure_id' => ['nullable', Rule::exists('structures', 'id')],
            'responsable_user_id' => ['nullable', Rule::exists('users', 'id')],
            'date_detection' => ['nullable', 'date'],
            'plan_action' => ['nullable', 'string'],
            'echeance' => ['nullable', 'date'],
            'statut' => ['required', Rule::in(array_keys(NonConformite::STATUTS))],
            'taux_avancement' => ['nullable', 'integer', 'between:0,100'],
        ]);
    }

    private function options(NonConformite $nc): array
    {
        return [
            'nc' => $nc,
            'sources' => NonConformite::SOURCES,
            'gravites' => NonConformite::GRAVITES,
            'statuts' => NonConformite::STATUTS,
            'controles' => ControleInterne::orderBy('code')->get(['id', 'code', 'libelle']),
            'missions' => MissionAudit::orderByDesc('id')->get(['id', 'numero', 'libelle']),
            'structures' => Structure::orderBy('code')->get(['id', 'code', 'libelle', 'sigle']),
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
        ];
    }
}
