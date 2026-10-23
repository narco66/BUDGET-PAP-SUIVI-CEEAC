<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\IncidentAction;
use App\Models\IncidentCategorie;
use App\Models\Structure;
use App\Models\User;
use App\Http\Requests\IncidentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Numero;
use Illuminate\View\View;

/**
 * Gestion des incidents techniques (ITIL) : Déclaration → Diagnostic → Correction → Validation → Clôture.
 * Permissions du module `incident` : voir / creer (déclarer) / modifier (traiter) / valider (clôturer/rejeter).
 */
class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('incident.voir'), 403);

        $incidents = Incident::with(['categorie', 'declarant', 'assigne'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('gravite'), fn ($q) => $q->where('gravite', $request->gravite))
            ->when($request->filled('categorie'), fn ($q) => $q->where('incident_categorie_id', $request->integer('categorie')))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return view('incidents.index', [
            'incidents' => $incidents,
            'categories' => IncidentCategorie::orderBy('ordre')->get(),
            'gravites' => Incident::GRAVITES,
            'statuts' => Incident::STATUTS,
            'synthese' => [
                'total' => Incident::count(),
                'ouverts' => Incident::ouvert()->count(),
                'critiques_ouverts' => Incident::where('gravite', 'critique')->ouvert()->count(),
                'clos' => Incident::where('statut', 'clos')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('incident.creer'), 403);

        return view('incidents.form', $this->options(new Incident(['gravite' => 'moyenne', 'source' => 'manuelle', 'statut' => 'ouvert'])));
    }

    public function store(IncidentRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('incident.creer'), 403);
        $incident = Incident::create($request->validated() + [
            'numero' => Numero::suivant('incidents', 'INC', now()->year),
            'declare_par' => $request->user()->id,
            'statut' => 'ouvert',
            'date_detection' => now(),
        ]);
        $this->journaliser($incident, 'communication', 'Incident déclaré', null, null, 'ouvert');

        return redirect()->route('incidents.show', $incident)->with('succes', "Incident {$incident->numero} déclaré.");
    }

    public function show(Request $request, Incident $incident): View
    {
        abort_unless($request->user()->can('incident.voir'), 403);
        $incident->load(['categorie', 'declarant', 'assigne', 'structure', 'actions.auteur']);

        return view('incidents.show', [
            'incident' => $incident,
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, Incident $incident): View
    {
        abort_unless($request->user()->can('incident.modifier'), 403);

        return view('incidents.form', $this->options($incident));
    }

    public function update(IncidentRequest $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->can('incident.modifier'), 403);
        $incident->update($request->validated());

        return redirect()->route('incidents.show', $incident)->with('succes', "Incident {$incident->numero} mis à jour.");
    }

    /** Avancement du cycle ITIL vers l'étape suivante (diagnostic → correction → validation → résolu). */
    public function avancer(IncidentRequest $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->can('incident.modifier'), 403);
        $next = $incident->prochainStatut();

        if ($incident->estClos() || $next === null || $next === 'clos') {
            return back()->with('erreur', 'Aucune étape suivante : utilisez « Clôturer ».');
        }

        $data = $request->validated();
        $avant = $incident->statut;
        $maj = ['statut' => $next];
        if (! empty($data['assigne_a'])) {
            $maj['assigne_a'] = $data['assigne_a'];
        }
        if ($next === 'resolu') {
            $maj['date_resolution'] = now();
        }
        $incident->update($maj);

        $type = ['en_diagnostic' => 'diagnostic', 'en_correction' => 'correction', 'en_validation' => 'validation', 'resolu' => 'validation'][$next] ?? 'communication';
        $this->journaliser($incident, $type, Incident::STATUTS[$next], $data['observation'] ?? null, $avant, $next);

        return back()->with('succes', 'Incident passé à : '.Incident::STATUTS[$next].'.');
    }

    /** Clôture de l'incident (résolution requise). */
    public function cloturer(IncidentRequest $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->can('incident.valider'), 403);
        abort_if($incident->estClos(), 422);
        $data = $request->validated();
        $avant = $incident->statut;
        $incident->update($data + ['statut' => 'clos', 'date_resolution' => $incident->date_resolution ?? now()]);
        $this->journaliser($incident, 'cloture', 'Incident clôturé', $data['resolution'], $avant, 'clos');

        return back()->with('succes', "Incident {$incident->numero} clôturé.");
    }

    /** Rejet de l'incident (faux positif / non recevable). */
    public function rejeter(IncidentRequest $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->can('incident.valider'), 403);
        abort_if($incident->estClos(), 422);
        $data = $request->validated();
        $avant = $incident->statut;
        $incident->update(['statut' => 'rejete']);
        $this->journaliser($incident, 'rejet', 'Incident rejeté', $data['motif'], $avant, 'rejete');

        return back()->with('succes', "Incident {$incident->numero} rejeté.");
    }

    /** Ajout d'une action au journal de l'incident (sans changement de statut). */
    public function storeAction(IncidentRequest $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->can('incident.modifier'), 403);
        $data = $request->validated();
        $this->journaliser($incident, $data['type'], $data['libelle'], $data['description'] ?? null, $incident->statut, $incident->statut);

        return back()->with('succes', 'Action enregistrée.');
    }

    private function journaliser(Incident $incident, string $type, string $libelle, ?string $description, ?string $avant, ?string $apres): void
    {
        IncidentAction::create([
            'incident_id' => $incident->id,
            'auteur_user_id' => request()->user()?->id,
            'type' => $type, 'libelle' => $libelle, 'description' => $description,
            'statut_avant' => $avant, 'statut_apres' => $apres, 'created_at' => now(),
        ]);
    }

    private function options(Incident $incident): array
    {
        return [
            'incident' => $incident,
            'categories' => IncidentCategorie::orderBy('ordre')->get(),
            'gravites' => Incident::GRAVITES,
            'sources' => Incident::SOURCES,
            'structures' => Structure::orderBy('code')->get(['id', 'code', 'libelle', 'sigle']),
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
        ];
    }
}
