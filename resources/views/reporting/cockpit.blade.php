@extends('layouts.app')
@section('titre', 'Tableau de bord exécutif')
@php
    $f = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $p = fn ($n) => number_format((float) $n, 1, ',', ' ').' %';
    // Montants compacts pour les cartes : 40 305 795 803 → « 40,3 Md ».
    $c = function ($n) use ($f) {
        $a = abs((float) $n);

        return $a >= 1e9 ? number_format($n / 1e9, 1, ',', ' ').' Md' : ($a >= 1e6 ? number_format($n / 1e6, 1, ',', ' ').' M' : $f($n));
    };
    $g = $data['global'];
    // Reliquat (lignes non rattachées à un axe) libellé « Hors PAP » ; il n'ouvre pas de détail par axe.
    $axe = $data['parAxe']->map(fn ($r) => ['x' => ($r['code'] ?? '—') === '—' ? 'Hors PAP' : $r['code'], 'code' => ($r['code'] ?? '—') === '—' ? null : $r['code'], 'intitule' => $r['intitule'] ?? '', 'ouvert' => $r['ouvert'], 'paye' => $r['paye']])->values();
    $cat = $data['parCategorie']->map(fn ($r) => ['x' => trim(($r['code'] ?? '').' '.\Illuminate\Support\Str::limit($r['intitule'] ?? '', 18)), 'v' => $r['ouvert'], 'code' => $r['code'] ?? '—'])->values();
    $bail = $data['parBailleur']['ceeac_ptf']->map(fn ($r) => ['x' => $r['type'], 'v' => $r['paye']])->values();
    $struct = $data['parStructure']->map(fn ($r) => ['x' => $r['libelle'], 'ouvert' => $r['ouvert'], 'paye' => $r['paye'], 'label' => $r['libelle']])->values();
    $evo = $data['evolution']->values();
    $chaine = $data['chaine'] ?? [];
    $couleurTaux = fn ($t) => $t >= 75 ? 'success' : ($t >= 40 ? 'warning' : 'danger');
@endphp

@push('styles')<style>
    .ck-kpi .libelle { font-size: .75rem; color: var(--bs-secondary-color); text-transform: uppercase; letter-spacing: .03em; }
    .ck-kpi .valeur { font-size: 1.45rem; font-weight: 700; line-height: 1.15; font-variant-numeric: tabular-nums; }
    .ck-kpi .detail { font-size: .75rem; color: var(--bs-secondary-color); }
    .ck-etape { border-left: 4px solid var(--bs-primary); }
    .ck-num { font-variant-numeric: tabular-nums; }
</style>@endpush

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="{{ route('reporting.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Reporting & Analyse</a>
        <h4 class="fw-bold mb-0">Tableau de bord exécutif</h4>
        <span class="text-muted small">Cockpit décisionnel · Exercice <span class="badge bg-primary">{{ $exercice->annee }}</span>
            · calculé à {{ \App\Support\Horodatage::local($data['calcule_le'] ?? now(), 'H:i') }}</span>
        <span class="badge bg-primary-subtle text-primary ms-1">Profil : {{ $persona['persona'] ?? '—' }}</span>
        <span id="cockpit-live-badge" class="badge bg-secondary ms-1" title="Actualisation automatique des indicateurs">Sync…</span>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label for="ck-exercice" class="small text-muted mb-0">Exercice</label>
        <select id="ck-exercice" name="exercice" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
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

{{-- Alertes (mêmes règles que les écrans de détail) --}}
<div class="mb-3">
    @foreach ($data['alertes'] as $a)
        <div class="alert alert-{{ $a['niveau'] }} py-2 mb-2 d-flex justify-content-between align-items-center gap-2">
            <span><i class="bi bi-{{ $a['niveau'] === 'success' ? 'check-circle' : 'exclamation-triangle' }} me-1"></i>{{ $a['message'] }}</span>
            @if (!empty($a['lien']))<a href="{{ $a['lien'] }}" class="alert-link small text-nowrap">Voir le détail →</a>@endif
        </div>
    @endforeach
</div>

{{-- Indicateurs budgétaires --}}
<div class="row g-3 mb-3">
    @foreach ([
        ['Crédits alloués', $c($g['alloue']), $g['revise_delta'] ? 'dont révision '.$c($g['revise_delta']) : 'ouvert '.$c($g['ouvert']), 'kpi-ouvert', 'primary', 'bi-wallet2'],
        ['Engagé', $c($g['engage']), $p($g['taux_engagement']).' du crédit alloué', 'kpi-engage', 'warning', 'bi-bookmark-check'],
        ['Payé', $c($g['paye']), 'exécution '.$p($g['taux_execution']).' du crédit', 'kpi-paye', 'success', 'bi-cash-coin'],
        ['Reste à payer', $c($g['reste_a_payer']), 'ordonnancé non encore payé', 'kpi-reste-payer', $g['reste_a_payer'] > 0 ? 'danger' : 'success', 'bi-hourglass-split'],
        ['Disponible à engager', $c($g['disponible']), 'crédit alloué − engagé', 'kpi-disponible', 'info', 'bi-piggy-bank'],
        ['Réalisation PAP', $p($data['pap']['physique']), $data['pap']['activites'].' activités · financier '.$p($data['pap']['financier']), 'kpi-pap-physique', 'secondary', 'bi-diagram-3'],
    ] as [$lib, $val, $detail, $id, $couleur, $icone])
    <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100 border-start border-4 border-{{ $couleur }} ck-kpi"><div class="card-body py-2">
        <div class="libelle"><i class="bi {{ $icone }} me-1"></i>{{ $lib }}</div>
        <div class="valeur" id="{{ $id }}">{{ $val }}</div>
        <div class="detail" id="{{ $id }}-detail">{{ $detail }}</div>
    </div></div></div>
    @endforeach
</div>

{{-- Chaîne d'exécution : entonnoir des étapes, chaque taux rapporté à l'étape précédente --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-bold"><i class="bi bi-arrow-right-circle me-1"></i>Chaîne d'exécution de la dépense</span>
        @canany(['suivi_financier.view', 'tableau_bord.voir'])
            <a href="{{ route('suivi-financier.index', ['exercice' => $exercice->id]) }}" class="small">Suivi financier détaillé →</a>
        @endcanany
    </div>
    <div class="card-body">
        <div class="row g-2">
            @foreach ($chaine as $i => $etape)
            <div class="col-6 col-lg-3">
                <div class="p-2 rounded bg-body-tertiary ck-etape h-100">
                    <div class="small text-muted">{{ $i + 1 }}. {{ $etape['etape'] }}</div>
                    <div class="fw-bold fs-5 ck-num" id="ck-chaine-{{ $i }}" title="{{ $f($etape['montant']) }} XAF">{{ $c($etape['montant']) }}</div>
                    <div class="d-flex justify-content-between small"><span class="text-muted">{{ $etape['base'] }}</span>
                        <span class="fw-semibold text-{{ $couleurTaux($etape['taux']) }}" id="ck-chaine-taux-{{ $i }}">{{ $p($etape['taux']) }}</span></div>
                    <div class="progress mt-1" style="height:5px" role="progressbar" aria-label="{{ $etape['etape'] }} : {{ $p($etape['taux']) }} {{ $etape['base'] }}"
                         aria-valuenow="{{ min(100, $etape['taux']) }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-{{ $couleurTaux($etape['taux']) }}" style="width: {{ min(100, $etape['taux']) }}%"></div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <div class="small text-muted mt-2">Montants en XAF ; survolez une valeur pour le montant exact. Base : lignes budgétaires de l'exercice (compteurs d'exécution).</div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Décaissements mensuels et cumulés</h6><div id="cEvo" role="img" aria-label="Décaissements mensuels et cumul de l'exercice"></div></div></div></div>
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Répartition par nature <small class="text-muted fw-normal">(cliquez pour le détail)</small></h6><div id="cCat" role="img" aria-label="Répartition des crédits par nature"></div></div></div></div>
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Exécution par axe — ouvert et payé <small class="text-muted fw-normal">(cliquez pour le détail)</small></h6><div id="cAxe" role="img" aria-label="Crédits ouverts et payés par axe"></div></div></div></div>
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Financement CEEAC et PTF (payé)</h6><div id="cBail" role="img" aria-label="Montants payés par source de financement"></div></div></div></div>
    <div class="col-12"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3">Répartition par département — ouvert et payé <small class="text-muted fw-normal">(cliquez pour le détail)</small></h6><div id="cStruct" role="img" aria-label="Crédits ouverts et payés par département"></div></div></div></div>
</div>

{{-- Synthèse Suivi-Évaluation (performance) --}}
@include('reporting._synthese_se', ['suiviEval' => $suiviEval ?? []])

{{-- Activités en retard (suivi opérationnel) --}}
<div id="activites-retard">
@include('reporting._activites_retard')
</div>
@endsection

@push('scripts')
@if (!empty($reverb['enabled']))
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>window.__reverbConfig = @json($reverb);</script>
@vite(['resources/js/echo.js', 'resources/js/charts.js', 'resources/js/cockpit-live.js'])
@else
@vite(['resources/js/charts.js', 'resources/js/cockpit-live.js'])
@endif
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
// Après exécution des modules Vite (ApexCharts) : un module est différé, ce script ne l'est pas.
document.addEventListener('DOMContentLoaded', function () {
    if (!window.ApexCharts) { return; }
    const drillEnabled = document.getElementById('cockpit-live-root')?.dataset.drillEnabled === '1';
    const onDrill = (niveau, parent, title) => drillEnabled && window.cockpitDrill?.(niveau, parent, title);
    const nf = new Intl.NumberFormat('fr-FR');
    const compact = v => { const a = Math.abs(v);
        if (a >= 1e9) return (v/1e9).toLocaleString('fr-FR',{maximumFractionDigits:1})+' Md';
        if (a >= 1e6) return (v/1e6).toLocaleString('fr-FR',{maximumFractionDigits:0})+' M';
        if (a >= 1e3) return (v/1e3).toLocaleString('fr-FR',{maximumFractionDigits:0})+' k'; return nf.format(v); };
    const money = { y: { formatter: v => nf.format(Math.round(v)) + ' XAF' } };
    const base = { chart:{fontFamily:'inherit',toolbar:{show:false},animations:{enabled:false}}, noData:{text:'Aucune donnée'} };
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

    const evo = @json($evo);
    const mois = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    mount('cEvo', '#cEvo', { ...base, chart:{...base.chart,type:'line',height:300},
        series:[{name:'Décaissements du mois',type:'column',data:evo.map(e=>e.montant)},{name:'Cumul',type:'line',data:evo.map(e=>e.cumul)}],
        xaxis:{categories:mois}, yaxis:{labels:{formatter:compact}}, stroke:{width:[0,3],curve:'smooth'},
        colors:['#0a4a8f','#198754'], dataLabels:{enabled:false}, legend:{position:'top'}, tooltip:{ shared:true, ...money } });

    const cat = @json($cat);
    mount('cCat', '#cCat', { ...base, chart:{...base.chart,type:'donut',height:300,
        events:{ dataPointSelection: (_e, _ctx, cfg) => {
            const item = cat[cfg.dataPointIndex];
            if (item?.code) onDrill('categorie', item.code, 'Nature : ' + item.x);
        }}},
        series:cat.map(c=>c.v), labels:cat.map(c=>c.x), legend:{position:'bottom'}, tooltip:money,
        plotOptions:{pie:{donut:{labels:{show:true,total:{show:true,label:'Total',formatter:(w)=>compact(w.globals.seriesTotals.reduce((a,b)=>a+b,0))}}}}} });

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
        yaxis:{labels:{formatter:compact}}, colors:['#0a4a8f','#198754'], dataLabels:{enabled:false}, legend:{position:'top'}, tooltip:money });
});
</script>
@endpush
