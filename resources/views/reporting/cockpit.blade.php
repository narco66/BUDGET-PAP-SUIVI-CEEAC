@extends('layouts.app')
@section('titre', 'Tableau de bord exécutif')
@php
    $f = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $g = $data['global'];
    $axe = $data['parAxe']->map(fn ($r) => ['x' => $r['code'] ?? '—', 'ouvert' => $r['ouvert'], 'paye' => $r['paye']]);
    $cat = $data['parCategorie']->map(fn ($r) => ['x' => trim(($r['code'] ?? '').' '.\Illuminate\Support\Str::limit($r['intitule'] ?? '', 18)), 'v' => $r['ouvert'], 'code' => $r['code'] ?? '—']);
    $bail = $data['parBailleur']['ceeac_ptf']->map(fn ($r) => ['x' => $r['type'], 'v' => $r['paye']]);
    $struct = $data['parStructure']->map(fn ($r) => ['x' => $r['libelle'], 'ouvert' => $r['ouvert'], 'paye' => $r['paye'], 'label' => $r['libelle']]);
    $evo = $data['evolution'];
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('reporting.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Reporting & Analyse</a>
        <h4 class="fw-bold mb-0">Tableau de bord exécutif</h4>
        <span class="text-muted small">Cockpit décisionnel · Exercice <span class="badge bg-primary">{{ $exercice->annee }}</span></span>
        <span class="badge bg-primary-subtle text-primary ms-1">Profil : {{ $persona['persona'] ?? '—' }}</span>
        <span id="cockpit-live-badge" class="badge bg-secondary ms-2">Sync…</span>
    </div>
    <form method="GET">
        <select name="exercice" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
            @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice->id === $ex->id)>{{ $ex->annee }}</option>@endforeach
        </select>
    </form>
</div>

<div id="cockpit-live-root" class="d-none"
    data-exercice-id="{{ $exercice->id }}"
    data-cache-version="{{ \App\Support\CockpitCache::version() }}"
    data-snapshot-url="{{ route('reporting.cockpit.live.snapshot') }}"
    data-drilldown-url="{{ route('reporting.cockpit.live.drilldown') }}"
    data-ping-url="{{ route('reporting.cockpit.live.ping') }}"
    data-poll-seconds="{{ config('cockpit.live.poll_seconds', 15) }}"
    data-broadcast-channel="cockpit.{{ $exercice->id }}"
    data-drill-enabled="{{ ($persona['persona_key'] ?? '') !== 'consultation' ? '1' : '0' }}"
></div>

<div id="cockpit-drill-panel" class="card border-0 shadow-sm mb-3 d-none">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold" id="cockpit-drill-title">Détail</span>
        <button type="button" class="btn-close" aria-label="Fermer" onclick="document.getElementById('cockpit-drill-panel').classList.add('d-none')"></button>
    </div>
    <div class="card-body p-0" id="cockpit-drill-body"></div>
</div>

{{-- Synthèse Suivi-Évaluation (performance) --}}
@include('reporting._synthese_se', ['suiviEval' => $suiviEval ?? []])

{{-- Alertes --}}
<div class="mb-3">
    @foreach ($data['alertes'] as $a)
        <div class="alert alert-{{ $a['niveau'] }} py-2 mb-2"><i class="bi bi-{{ $a['niveau'] === 'success' ? 'check-circle' : 'exclamation-triangle' }} me-1"></i>{{ $a['message'] }}</div>
    @endforeach
</div>

{{-- KPI --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Taux d'exécution budgétaire</div><div class="fs-3 fw-bold text-success" id="kpi-taux-exec">{{ $g['taux_execution'] }}%</div>
        <div class="progress" style="height:6px"><div class="progress-bar bg-success" style="width:{{ min($g['taux_execution'],100) }}%"></div></div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Taux d'engagement</div><div class="fs-3 fw-bold text-warning" id="kpi-taux-engage">{{ $g['taux_engagement'] }}%</div>
        <div class="progress" style="height:6px"><div class="progress-bar bg-warning" style="width:{{ min($g['taux_engagement'],100) }}%"></div></div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Réalisation physique PAP</div><div class="fs-3 fw-bold text-primary" id="kpi-pap-physique">{{ $data['pap']['physique'] }}%</div>
        <small class="text-muted">{{ $data['pap']['activites'] }} activités · fin. {{ $data['pap']['financier'] }}%</small>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Crédits ouverts</div><div class="fs-5 fw-bold text-dark" id="kpi-ouvert">{{ $f($g['ouvert']) }}</div>
        <small class="text-muted">Payé : <span id="kpi-paye">{{ $f($g['paye']) }}</span> XAF</small>
    </div></div></div>
</div>

@canany(['suivi_financier.view', 'tableau_bord.voir'])
<div class="mb-3">
    <a href="{{ route('suivi-financier.index', ['exercice' => $exercice->id]) }}" class="card border-0 shadow-sm text-decoration-none text-dark">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="text-muted small">Module Suivi Financier</div>
                <div class="fw-bold">Restes à payer : {{ $f($g['reste_a_payer'] ?? max($g['ordonnance'] - $g['paye'], 0)) }} XAF</div>
                <small class="text-muted">Engagement {{ $g['taux_engagement'] }}% · Exécution {{ $g['taux_execution'] }}%</small>
            </div>
            <span class="btn btn-sm btn-outline-primary">Ouvrir le hub →</span>
        </div>
    </a>
</div>
@endcanany

<div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Chaîne d'exécution budgétaire</h6><div id="cChaine"></div></div></div></div>
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Répartition par catégorie</h6><div id="cCat"></div></div></div></div>
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Exécution par axe (ouvert vs payé)</h6><div id="cAxe"></div></div></div></div>
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Financement CEEAC vs PTF (payé)</h6><div id="cBail"></div></div></div></div>
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Répartition par département</h6><div id="cStruct"></div></div></div></div>
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Évolution mensuelle des décaissements</h6><div id="cEvo"></div></div></div></div>
</div>

{{-- Activités en retard (suivi opérationnel) --}}
@include('reporting._activites_retard')
@endsection

@push('scripts')
@if (!empty($reverb['enabled']))
<script>window.__reverbConfig = @json($reverb);</script>
@vite(['resources/js/echo.js', 'resources/js/charts.js', 'resources/js/cockpit-live.js'])
@else
@vite(['resources/js/charts.js', 'resources/js/cockpit-live.js'])
@endif
<script>
(function () {
    const drillEnabled = document.getElementById('cockpit-live-root')?.dataset.drillEnabled === '1';
    const onDrill = (niveau, parent, title) => drillEnabled && window.cockpitDrill?.(niveau, parent, title);
    const nf = new Intl.NumberFormat('fr-FR');
    const compact = v => { const a = Math.abs(v);
        if (a >= 1e9) return (v/1e9).toLocaleString('fr-FR',{maximumFractionDigits:1})+' Md';
        if (a >= 1e6) return (v/1e6).toLocaleString('fr-FR',{maximumFractionDigits:0})+' M';
        if (a >= 1e3) return (v/1e3).toLocaleString('fr-FR',{maximumFractionDigits:0})+' k'; return nf.format(v); };
    const money = { y: { formatter: v => nf.format(Math.round(v)) + ' XAF' } };
    const base = { chart:{fontFamily:'inherit',toolbar:{show:false}}, noData:{text:'Aucune donnée'} };
    window.__cockpitCharts = {};
    const mount = (key, sel, opts) => {
        const el = document.querySelector(sel);
        if (!el) return null;
        try {
            const chart = new ApexCharts(el, opts);
            chart.render();
            window.__cockpitCharts[key] = chart;
            return chart;
        } catch (e) { console.error(sel, e); return null; }
    };

    mount('cChaine', '#cChaine', { ...base, chart:{...base.chart,type:'bar',height:300},
        plotOptions:{bar:{horizontal:true,distributed:true,borderRadius:4}},
        series:[{data:[
            {x:'Ouvert',y:{{ (int)$g['ouvert'] }}},{x:'Engagé',y:{{ (int)$g['engage'] }}},
            {x:'Liquidé',y:{{ (int)$g['liquide'] }}},{x:'Ordonnancé',y:{{ (int)$g['ordonnance'] }}},{x:'Payé',y:{{ (int)$g['paye'] }}}]}],
        colors:['#0a4a8f','#fd7e14','#0dcaf0','#6c757d','#198754'], legend:{show:false},
        dataLabels:{formatter:compact}, xaxis:{labels:{formatter:compact}}, tooltip:money });

    const cat = @json($cat);
    mount('cCat', '#cCat', { ...base, chart:{...base.chart,type:'donut',height:300,
        events:{ dataPointSelection: (_e, _ctx, cfg) => {
            const item = cat[cfg.dataPointIndex];
            if (item?.code) onDrill('categorie', item.code, 'Nature : ' + item.x);
        }}},
        series:cat.map(c=>c.v), labels:cat.map(c=>c.x), legend:{position:'bottom'}, tooltip:money });

    const axe = @json($axe);
    mount('cAxe', '#cAxe', { ...base, chart:{...base.chart,type:'bar',height:320,
        events:{ dataPointSelection: (_e, _ctx, cfg) => {
            const item = axe[cfg.dataPointIndex];
            if (item?.code) onDrill('axe', item.code, 'Axe : ' + item.x);
        }}},
        series:[{name:'Ouvert',data:axe.map(a=>a.ouvert)},{name:'Payé',data:axe.map(a=>a.paye)}],
        xaxis:{categories:axe.map(a=>a.x)}, yaxis:{labels:{formatter:compact}},
        colors:['#0a4a8f','#198754'], dataLabels:{enabled:false}, legend:{position:'top'}, tooltip:money });

    const bail = @json($bail);
    mount('cBail', '#cBail', { ...base, chart:{...base.chart,type:'pie',height:300},
        series:bail.map(b=>b.v), labels:bail.map(b=>b.x), colors:['#0a4a8f','#20c997','#adb5bd'], legend:{position:'bottom'}, tooltip:money });

    const struct = @json($struct);
    mount('cStruct', '#cStruct', { ...base, chart:{...base.chart,type:'bar',height:320,
        events:{ dataPointSelection: (_e, _ctx, cfg) => {
            const item = struct[cfg.dataPointIndex];
            if (item?.label) onDrill('structure', item.label, 'Structure : ' + item.x);
        }}},
        series:[{name:'Ouvert',data:struct.map(s=>s.ouvert)},{name:'Payé',data:struct.map(s=>s.paye)}],
        xaxis:{categories:struct.map(s=>s.x),labels:{rotate:-45,style:{fontSize:'10px'}}},
        yaxis:{labels:{formatter:compact}}, colors:['#0a4a8f','#198754'], dataLabels:{enabled:false}, tooltip:money });

    const evo = @json($evo);
    const mois = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    mount('cEvo', '#cEvo', { ...base, chart:{...base.chart,type:'area',height:320},
        series:[{name:'Décaissements',data:evo.map(e=>e.montant)}],
        xaxis:{categories:mois}, yaxis:{labels:{formatter:compact}},
        colors:['#0a4a8f'], stroke:{curve:'smooth',width:2}, fill:{opacity:.2}, tooltip:money });
})();
</script>
@endpush
