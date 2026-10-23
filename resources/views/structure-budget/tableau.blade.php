@extends('layouts.app')
@section('titre', 'Tableau de bord — Structure du budget')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

@push('styles')<style>.sb-mt{font-variant-numeric:tabular-nums}</style>@endpush

@section('contenu')
<a href="{{ route('structure-budget.arbre', ['exercice' => $exercice?->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Structure du budget</a>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Tableau de bord — Structure du budget</h4>
        <span class="text-muted small">Consolidation par organigramme (rollup) et par axes programmatique / financier</span>
    </div>
    <form method="GET"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
    </select></form>
</div>

@if(!empty($anomalies))
<div class="mb-3">
    @foreach ($anomalies as $a)
        <div class="alert alert-{{ $a['niveau'] === 'danger' ? 'danger' : ($a['niveau'] === 'warning' ? 'warning' : 'info') }} py-2 mb-2 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle me-1"></i>{{ $a['message'] }}</span>
            @if($a['code'] === 'LIAISON_BUDGET_DESYNC' && ($peutReconcilier ?? false))
                <form method="POST" action="{{ route('structure-budget.reconcilier', ['exercice' => $exercice?->id]) }}" class="ms-2">@csrf
                    <button class="btn btn-sm btn-outline-dark">Réconcilier</button>
                </form>
            @endif
        </div>
    @endforeach
</div>
@endif

@if($evolution)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up me-1"></i>Évolution budgétaire {{ $evolution['annee_precedente'] }} → {{ $evolution['annee_courante'] }}</div>
    <div class="card-body row g-3 small">
        <div class="col-md-3"><strong>Ouvert {{ $evolution['annee_precedente'] }}</strong><br><span class="sb-mt fs-5">{{ $fmt($evolution['ouvert_precedent']) }}</span></div>
        <div class="col-md-3"><strong>Ouvert {{ $evolution['annee_courante'] }}</strong><br><span class="sb-mt fs-5">{{ $fmt($evolution['ouvert_courant']) }}</span></div>
        <div class="col-md-3"><strong>Variation</strong><br>
            <span class="sb-mt fs-5 {{ $evolution['delta_ouvert'] >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $evolution['delta_ouvert'] >= 0 ? '+' : '' }}{{ $fmt($evolution['delta_ouvert']) }}
                @if($evolution['delta_pct'] !== null) ({{ $evolution['delta_pct'] }} %) @endif
            </span>
        </div>
        <div class="col-md-3"><strong>Lignes</strong><br>{{ $evolution['nb_lignes_precedent'] }} → {{ $evolution['nb_lignes_courant'] }}</div>
    </div>
</div>
@endif

@if(($repartitionSens ?? collect())->isNotEmpty())
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Répartition par sens budgétaire</div>
    <div class="card-body">
        @php $maxSens = max(1, (float) $repartitionSens->max('ouvert')); @endphp
        @foreach($repartitionSens as $r)
            <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1"><span class="text-capitalize">{{ str_replace('_', ' ', $r->sens) }}</span><span class="sb-mt">{{ $fmt($r->ouvert) }} ({{ $r->nb }})</span></div>
                <div class="progress" style="height:8px"><div class="progress-bar bg-primary" style="width:{{ min(100, round($r->ouvert / $maxSens * 100)) }}%"></div></div>
            </div>
        @endforeach
    </div>
</div>
@endif

@if(($ecartsPap ?? collect())->isNotEmpty())
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold text-warning">Écarts PAP vs budget (activités dépassées)</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Activité</th><th class="text-end">Montant PAP</th><th class="text-end">Total lignes</th><th class="text-end">Écart</th></tr></thead>
        <tbody>@foreach($ecartsPap as $e)<tr>
            <td class="small">{{ $e->code }}</td>
            <td class="text-end small sb-mt">{{ $fmt($e->montant_pap) }}</td>
            <td class="text-end small sb-mt">{{ $fmt($e->montant_lignes) }}</td>
            <td class="text-end small text-danger sb-mt">+{{ $fmt($e->ecart) }}</td>
        </tr>@endforeach</tbody>
    </table></div>
</div>
@endif

<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold sb-mt">{{ $fmt($synthese['ouvert']) }}</div><div class="text-muted small">Ouvert (XAF)</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-primary sb-mt">{{ $fmt($synthese['engage']) }}</div><div class="text-muted small">Engagé</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-success sb-mt">{{ $fmt($synthese['paye']) }}</div><div class="text-muted small">Payé</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold">{{ $synthese['nb_lignes'] }}</div><div class="text-muted small">Lignes budgétaires</div></div></div></div>
</div>

<div class="row g-3 mt-1">
    @foreach ([
        ['Budget par Département', 'bi-diagram-3', $parDepartement],
        ['Budget par Direction', 'bi-diagram-2', $parDirection],
        ['Budget par Service', 'bi-list-task', $parService],
    ] as [$titre, $icone, $rows])
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi {{ $icone }} me-1"></i>{{ $titre }}</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th class="text-end">Ouvert</th><th class="text-end">Engagé</th></tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr><td class="font-monospace small">{{ $r['code'] }}</td><td class="small">{{ \Illuminate\Support\Str::limit($r['libelle'], 18) }}</td>
                        <td class="text-end small sb-mt">{{ $fmt($r['ouvert']) }}</td><td class="text-end small text-primary sb-mt">{{ $fmt($r['engage']) }}</td></tr>
                @empty<tr><td colspan="4" class="text-center text-muted py-3">Aucune donnée.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3-fill me-1"></i>Budget par Pilier (programmatique)</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Pilier</th><th class="text-end">Ouvert</th><th class="text-end">Payé</th></tr></thead>
                <tbody>
                @forelse ($parPilier as $p)
                    <tr><td class="small">{{ $p['code'] }} — {{ \Illuminate\Support\Str::limit($p['libelle'], 20) }}</td>
                        <td class="text-end small sb-mt">{{ $fmt($p['ouvert']) }}</td><td class="text-end small sb-mt">{{ $fmt($p['paye']) }}</td></tr>
                @empty<tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-1"></i>Budget par Source de financement</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Source</th><th class="text-end">Ouvert</th><th class="text-end">Engagé</th></tr></thead>
                <tbody>
                @forelse ($parBailleur as $s)
                    <tr><td class="small">{{ \Illuminate\Support\Str::limit($s['libelle'], 22) }}</td>
                        <td class="text-end small sb-mt">{{ $fmt($s['ouvert']) }}</td><td class="text-end small text-primary sb-mt">{{ $fmt($s['engage']) }}</td></tr>
                @empty<tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i>Budget par Partenaire (PTF)</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Partenaire</th><th class="text-end">Ouvert</th><th class="text-end">Engagé</th></tr></thead>
                <tbody>
                @forelse ($parPartenaire as $p)
                    <tr><td class="small">{{ $p['sigle'] ?? \Illuminate\Support\Str::limit($p['libelle'], 20) }}</td>
                        <td class="text-end small sb-mt">{{ $fmt($p['ouvert']) }}</td><td class="text-end small text-primary sb-mt">{{ $fmt($p['engage']) }}</td></tr>
                @empty<tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
