@extends('layouts.app')
@section('titre', 'Tableaux de bord décisionnels')
@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('contenu')
@php
    $tw = fn ($k) => in_array('*', $persona['tableaux_widgets']) || in_array($k, $persona['tableaux_widgets']);
@endphp
<form method="GET" class="mb-3 d-flex flex-wrap align-items-center gap-2">
    <select name="exercice" class="form-select form-select-sm" style="width:180px;" onchange="this.form.submit()">
        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id===$ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
    </select>
    <span class="badge bg-primary-subtle text-primary">Profil : {{ $persona['persona'] }}</span>
</form>

@if ($tw('budget_kpi') || $tw('pap_summary'))
{{-- KPIs --}}
<div class="row g-3 mb-3">
    @if ($tw('budget_kpi'))
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Crédits ouverts</div><div class="fs-5 fw-bold text-primary">{{ $fmt($budget['ouvert']) }}</div><small class="text-muted">XAF</small>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Taux d'engagement</div><div class="fs-4 fw-bold text-warning">{{ $budget['taux_engagement'] }}%</div>
        <div class="progress" style="height:6px;"><div class="progress-bar bg-warning" style="width:{{ min($budget['taux_engagement'],100) }}%"></div></div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Taux d'exécution (payé)</div><div class="fs-4 fw-bold text-success">{{ $budget['taux_execution'] }}%</div>
        <div class="progress" style="height:6px;"><div class="progress-bar bg-success" style="width:{{ min($budget['taux_execution'],100) }}%"></div></div>
    </div></div></div>
    @endif
    @if ($tw('pap_summary'))
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">PAP — activités</div><div class="fs-5 fw-bold">{{ $pap['activites'] }}</div>
        <small class="text-muted">Réal. phys. {{ $pap['taux_physique'] }}% · fin. {{ $pap['taux_financier'] }}%</small>
    </div></div></div>
    @endif
</div>
@endif
@if ($tw('budget_chain'))
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Taux de liquidation</div><div class="fs-5 fw-bold text-info">{{ $budget['taux_liquidation'] ?? 0 }}%</div>
        <small class="text-muted">Liquidé : {{ $fmt($budget['liquide'] ?? 0) }} XAF</small>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Taux d'ordonnancement</div><div class="fs-5 fw-bold text-secondary">{{ $budget['taux_ordonnancement'] ?? 0 }}%</div>
        <small class="text-muted">Ordonnancé : {{ $fmt($budget['ordonnance'] ?? 0) }} XAF</small>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Taux de paiement · reste à payer</div>
        <div class="fs-5 fw-bold text-success">{{ $budget['taux_paiement'] ?? 0 }}%</div>
        <small class="text-muted">Reste : {{ $fmt($budget['reste_a_payer'] ?? 0) }} XAF</small>
    </div></div></div>
</div>
@endif
@if ($tw('budget_reste'))
<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card border-0 shadow-sm border-start border-3 border-warning"><div class="card-body">
        <div class="text-muted small">Restes à engager</div><div class="fs-5 fw-bold text-warning">{{ $fmt($budget['reste_a_engager'] ?? 0) }}</div>
        <small class="text-muted">XAF</small>
    </div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm border-start border-3 border-danger"><div class="card-body">
        <div class="text-muted small">Restes à payer</div><div class="fs-5 fw-bold text-danger">{{ $fmt($budget['reste_a_payer'] ?? 0) }}</div>
        <small class="text-muted">XAF</small>
    </div></div></div>
</div>
@endif

@if ($tw('tresorerie_link'))
<div class="mb-3">
    <a href="{{ route('tableaux.tresorerie', ['exercice' => $exercice?->id]) }}" class="card border-0 shadow-sm text-decoration-none text-dark">
        <div class="card-body d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bank me-2 text-primary"></i>Trésorerie unifiée recettes / dépenses</span>
            <span class="btn btn-sm btn-outline-primary">Ouvrir →</span>
        </div>
    </a>
</div>
@endif

@if ($tw('recettes') && $recettes)
@php $fmtR = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp
<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="text-muted text-uppercase small mb-0"><i class="bi bi-piggy-bank me-1"></i>Recettes & ressources</h6>
    <a href="{{ route('recettes.index') }}" class="small">Détail →</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card shadow-sm border-start border-3 border-primary"><div class="card-body">
        <div class="text-muted small">Recettes réalisées</div><div class="fs-5 fw-bold text-success">{{ $fmtR($recettes['realise']) }}</div>
        <small class="text-muted">/ {{ $fmtR($recettes['prevu']) }} prévu · {{ $recettes['taux'] }}%</small>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm border-start border-3 border-success"><div class="card-body">
        <div class="text-muted small">Contributions recouvrées</div><div class="fs-5 fw-bold">{{ $fmtR($recettes['recouvre']) }}</div>
        <small class="text-muted">/ {{ $fmtR($recettes['appele']) }} appelé · {{ $recettes['taux_recouvrement'] }}%</small>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm border-start border-3 border-danger"><div class="card-body">
        <div class="text-muted small">Arriérés de contributions</div><div class="fs-5 fw-bold text-danger">{{ $fmtR($recettes['arriere']) }}</div>
        <small class="text-muted">XAF</small>
    </div></div></div>
    <div class="col-md-3"><div class="card shadow-sm border-start border-3 border-warning"><div class="card-body">
        <div class="text-muted small">Taux de recouvrement</div><div class="fs-5 fw-bold">{{ $recettes['taux_recouvrement'] }}%</div>
        <div class="progress mt-2" style="height:5px;"><div class="progress-bar bg-warning" style="width: {{ min($recettes['taux_recouvrement'], 100) }}%"></div></div>
    </div></div></div>
</div>
@endif

@if ($tw('charts_all'))
<div class="row g-3">
    <div class="col-md-7"><div class="card border-0 shadow-sm"><div class="card-body">
        <h6 class="fw-bold">Chaîne d'exécution budgétaire</h6><div id="chartChaine"></div>
    </div></div></div>
    <div class="col-md-5"><div class="card border-0 shadow-sm"><div class="card-body">
        <h6 class="fw-bold">Performance des indicateurs</h6><div id="chartFeux"></div>
    </div></div></div>
    <div class="col-md-7"><div class="card border-0 shadow-sm"><div class="card-body">
        <h6 class="fw-bold">Consommation par structure (ouvert vs payé)</h6><div id="chartStructure"></div>
    </div></div></div>
    <div class="col-md-5"><div class="card border-0 shadow-sm"><div class="card-body">
        <h6 class="fw-bold">Répartition par nature</h6><div id="chartNature"></div>
    </div></div></div>
</div>
@endif

@php
if ($tw('charts_all')) {
$nature = $parNature->map(fn ($n) => ['x' => $n->code.' '.\Illuminate\Support\Str::limit($n->intitule,18), 'ouvert' => (float)$n->ouvert, 'paye' => (float)$n->paye]);
$struct = $parStructure->map(fn ($s) => ['x' => $s->libelle, 'ouvert' => (float)$s->ouvert, 'paye' => (float)$s->paye]);
}
@endphp
@endsection

@push('scripts')
@if ($tw('charts_all'))
@vite('resources/js/charts.js')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
// Après exécution des modules Vite (ApexCharts) : un module est différé, ce script ne l'est pas.
document.addEventListener('DOMContentLoaded', function () {
const xaf = v => new Intl.NumberFormat('fr-FR').format(v);

new ApexCharts(document.querySelector('#chartChaine'), {
    chart:{type:'bar',height:300}, plotOptions:{bar:{horizontal:true,distributed:true}},
    series:[{data:[
        {x:'Ouvert',y:{{ (int)$budget['ouvert'] }}},{x:'Engagé',y:{{ (int)$budget['engage'] }}},
        {x:'Liquidé',y:{{ (int)$budget['liquide'] }}},{x:'Ordonnancé',y:{{ (int)$budget['ordonnance'] }}},
        {x:'Payé',y:{{ (int)$budget['paye'] }}}
    ]}],
    colors:['#0a4a8f','#fd7e14','#0dcaf0','#6c757d','#198754'], legend:{show:false},
    dataLabels:{formatter:xaf}, yaxis:{labels:{formatter:xaf}}
}).render();

// Anneau et secteurs : valeurs numériques, et message explicite si tout est nul (sinon ApexCharts affiche « NaN % »).
const vide = (sel, texte) => { const el = document.querySelector(sel); if (el) { el.innerHTML = '<div class="text-muted small text-center py-5">' + texte + '</div>'; } };
const feux = [{{ (int) $feux['vert'] }}, {{ (int) $feux['orange'] }}, {{ (int) $feux['rouge'] }}];
if (feux.reduce((a, b) => a + b, 0) === 0) {
    vide('#chartFeux', "Aucune mesure d'indicateur enregistrée pour l'exercice.");
} else {
    new ApexCharts(document.querySelector('#chartFeux'), {
        chart:{type:'donut',height:300}, series:feux,
        labels:['Vert','Orange','Rouge'], colors:['#198754','#fd7e14','#dc3545']
    }).render();
}

const struct = @json($struct);
new ApexCharts(document.querySelector('#chartStructure'), {
    chart:{type:'bar',height:320,stacked:false},
    series:[{name:'Ouvert',data:struct.map(s=>Number(s.ouvert))},{name:'Payé',data:struct.map(s=>Number(s.paye))}],
    xaxis:{categories:struct.map(s=>s.x),labels:{rotate:-45,style:{fontSize:'10px'}}},
    colors:['#0a4a8f','#198754'], yaxis:{labels:{formatter:xaf}}
}).render();

const nature = @json($nature);
if (nature.reduce((a, n) => a + Number(n.ouvert), 0) === 0) {
    vide('#chartNature', 'Aucun crédit ouvert par nature.');
} else {
    new ApexCharts(document.querySelector('#chartNature'), {
        chart:{type:'pie',height:300},
        series:nature.map(n=>Number(n.ouvert)), labels:nature.map(n=>n.x),
        tooltip:{y:{formatter:v=>xaf(Math.round(v))+' XAF'}}
    }).render();
}
});
</script>
@endif
@endpush
