<?php

namespace App\Http\Controllers;

use App\Models\ConstatAudit;
use App\Models\Exercice;
use App\Models\MissionAudit;
use App\Models\Structure;
use App\Models\User;
use App\Http\Requests\MissionAuditRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Numero;
use Illuminate\View\View;

/**
 * Missions d'audit (INTOSAI) : plan annuel, exécution, constats, recommandations et leur suivi.
 * Permissions du module `audit` : voir / creer (planifier) / modifier (exécuter) / valider (clôturer).
 */
class MissionAuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('audit.voir'), 403);

        $missions = MissionAudit::with(['structure', 'chefMission', 'exercice'])
            ->withCount(['constats', 'constats as constats_ouverts_count' => fn ($q) => $q->whereIn('statut', ['ouvert', 'en_cours'])])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return view('audit-missions.index', [
            'missions' => $missions,
            'statuts' => MissionAudit::STATUTS,
            'synthese' => [
                'total' => MissionAudit::count(),
                'ouvertes' => MissionAudit::ouverte()->count(),
                'cloturees' => MissionAudit::where('statut', 'cloturee')->count(),
                'constats_ouverts' => ConstatAudit::whereIn('statut', ['ouvert', 'en_cours'])->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('audit.creer'), 403);

        return view('audit-missions.form', $this->options(new MissionAudit(['type' => 'interne', 'statut' => 'planifiee'])));
    }

    public function store(MissionAuditRequest $request): RedirectResponse
    {
        abort_unless($request->user()->can('audit.creer'), 403);
        $data = $request->validated();
        $data['numero'] = Numero::suivant('missions_audit', 'MA', now()->year);
        $data['created_by'] = $request->user()->id;
        $mission = MissionAudit::create($data);

        return redirect()->route('audit-missions.show', $mission)->with('succes', "Mission {$mission->numero} planifiée.");
    }

    public function show(Request $request, MissionAudit $mission): View
    {
        abort_unless($request->user()->can('audit.voir'), 403);
        $mission->load(['structure', 'chefMission', 'exercice', 'constats.responsable']);

        return view('audit-missions.show', [
            'mission' => $mission,
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
            'gravites' => ConstatAudit::GRAVITES,
            'statutsConstat' => ConstatAudit::STATUTS,
        ]);
    }

    public function edit(Request $request, MissionAudit $mission): View
    {
        abort_unless($request->user()->can('audit.modifier'), 403);

        return view('audit-missions.form', $this->options($mission));
    }

    public function update(MissionAuditRequest $request, MissionAudit $mission): RedirectResponse
    {
        abort_unless($request->user()->can('audit.modifier'), 403);
        $mission->update($request->validated());

        return redirect()->route('audit-missions.show', $mission)->with('succes', "Mission {$mission->numero} mise à jour.");
    }

    /** Clôture de la mission (audit.valider). Bloquée si des constats restent ouverts (recommandations non bouclées). */
    public function cloturer(MissionAuditRequest $request, MissionAudit $mission): RedirectResponse
    {
        abort_unless($request->user()->can('audit.valider'), 403);
        $data = $request->validated();

        if ($mission->constatsOuverts() > 0) {
            return back()->with('erreur', 'Clôture impossible : '.$mission->constatsOuverts().' constat(s)/recommandation(s) encore ouvert(s).');
        }

        $mission->update($data + ['statut' => 'cloturee']);

        return back()->with('succes', "Mission {$mission->numero} clôturée.");
    }

    /** Ajout d'un constat + recommandation à la mission. */
    public function storeConstat(MissionAuditRequest $request, MissionAudit $mission): RedirectResponse
    {
        abort_unless($request->user()->can('audit.creer'), 403);
        abort_unless($mission->estModifiable(), 422, 'Mission clôturée.');

        $constat = ConstatAudit::create($request->validated() + [
            'numero' => Numero::suivant('constats_audit', 'CA', now()->year),
            'mission_audit_id' => $mission->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('succes', "Constat {$constat->numero} enregistré.");
    }

    /** Suivi d'une recommandation : mise à jour statut + taux d'avancement. */
    public function updateConstat(MissionAuditRequest $request, MissionAudit $mission, ConstatAudit $constat): RedirectResponse
    {
        abort_unless($request->user()->can('audit.modifier'), 403);
        abort_unless($constat->mission_audit_id === $mission->id, 404);

        $constat->update($request->validated());

        return back()->with('succes', "Recommandation {$constat->numero} mise à jour.");
    }

    private function options(MissionAudit $mission): array
    {
        return [
            'mission' => $mission,
            'types' => MissionAudit::TYPES,
            'statuts' => MissionAudit::STATUTS,
            'exercices' => Exercice::orderByDesc('annee')->get(),
            'structures' => Structure::orderBy('code')->get(['id', 'code', 'libelle', 'sigle']),
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
        ];
    }
}
