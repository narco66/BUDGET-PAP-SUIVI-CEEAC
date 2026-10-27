@extends('layouts.app')
@section('titre', 'Performance workflow')

@php
    $k = $data['kpi'];
    $f = $data['filtres'];
    // Durées lisibles : minutes, heures ou jours selon l'ordre de grandeur.
    $duree = fn ($h) => $h === null ? '—' : ($h < 1 ? max(1, (int) round($h * 60)).' min' : ($h < 48 ? number_format($h, 1, ',', ' ').' h' : number_format($h / 24, 1, ',', ' ').' j'));
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', ' ').' %';
    $couleurSla = fn ($v) => $v === null ? 'secondary' : ($v >= 90 ? 'success' : ($v >= 70 ? 'warning' : 'danger'));
    $q = fn (array $extra = []) => array_filter(array_merge(['jours' => $jours, 'module' => $f['module'], 'circuit' => $f['circuit']], $extra), fn ($v) => $v !== null && $v !== '');
    $libellesAction = ['workflow_demarre' => 'Démarrage', 'workflow_valide' => 'Validation', 'workflow_rejete' => 'Rejet', 'workflow_renvoye' => 'Retour', 'workflow_relance' => 'Relance'];
    $couleursAction = ['workflow_valide' => 'success', 'workflow_rejete' => 'danger', 'workflow_renvoye' => 'warning', 'workflow_demarre' => 'primary', 'workflow_relance' => 'info'];
@endphp

@push('styles')<style>
    .wf-kpi .valeur { font-size: 1.6rem; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.1; }
    .wf-kpi .libelle { font-size: .78rem; color: var(--bs-secondary-color); text-transform: uppercase; letter-spacing: .03em; }
    .wf-kpi .detail { font-size: .75rem; color: var(--bs-secondary-color); }
    .wf-num { font-variant-numeric: tabular-nums; }
    .wf-barre { height: 6px; }
</style>@endpush

@section('contenu')
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2"></i>Performance des circuits de validation</h4>
        <div class="text-muted small">Délais mesurés de l'activation d'une étape à la décision · période de {{ $jours }} jours · actualisé à {{ \App\Support\Horodatage::local(now(), 'H:i') }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('workflow.validations') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-inbox me-1"></i>Corbeille</a>
        <a href="{{ route('workflow.audit') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-journal-check me-1"></i>Audit</a>
        @can('workflow.audit')
        <a href="{{ route('workflow.dashboard.export', ['jours' => $jours]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export CSV</a>
        @endcan
    </div>
</div>

{{-- Filtres --}}
<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small mb-0" for="wf-jours">Période</label>
            <select id="wf-jours" name="jours" class="form-select form-select-sm">
                @foreach ([7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 180 => '6 mois', 365 => '12 mois'] as $p => $l)
                    <option value="{{ $p }}" @selected($jours === $p)>{{ $l }}</option>
                @endforeach
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0" for="wf-module">Module</label>
            <select id="wf-module" name="module" class="form-select form-select-sm">
                <option value="">Tous</option>
                @foreach ($data['modules'] as $m)<option value="{{ $m }}" @selected($f['module'] === $m)>{{ $m }}</option>@endforeach
            </select></div>
        <div class="col-md-4"><label class="form-label small mb-0" for="wf-circuit">Circuit</label>
            <select id="wf-circuit" name="circuit" class="form-select form-select-sm">
                <option value="">Tous les circuits</option>
                @foreach ($data['circuits'] as $code => $libelle)<option value="{{ $code }}" @selected($f['circuit'] === $code)>{{ $code }} — {{ $libelle }}</option>@endforeach
            </select></div>
        <div class="col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrer</button>
            <a href="{{ route('workflow.dashboard') }}" class="btn btn-sm btn-outline-secondary" title="Réinitialiser" aria-label="Réinitialiser"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

{{-- Indicateurs clés --}}
<div class="row g-3 mb-3">
    @foreach ([
        ['Dossiers en cours', $k['en_cours'], $k['taches_ouvertes'].' étape(s) en attente', 'primary', 'bi-hourglass-split'],
        ['Étapes en retard', $k['en_retard'], $k['taches_ouvertes'] ? $pct($k['taches_en_retard'] / max(1, $k['taches_ouvertes']) * 100).' des étapes en attente' : 'aucune étape en attente', $k['en_retard'] ? 'danger' : 'success', 'bi-alarm'],
        ['Délai moyen de traitement', $duree($k['delai_moyen_heures']), 'médiane '.$duree($k['delai_median_heures']).' · '.$k['decisions_periode'].' décision(s)', 'info', 'bi-stopwatch'],
        ['Respect des délais', $pct($k['taux_sla_pct']), 'décisions avant échéance', $couleurSla($k['taux_sla_pct']), 'bi-check2-circle'],
    ] as [$lib, $val, $detail, $couleur, $icone])
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100 border-start border-4 border-{{ $couleur }} wf-kpi"><div class="card-body py-2">
        <div class="libelle"><i class="bi {{ $icone }} me-1"></i>{{ $lib }}</div>
        <div class="valeur text-{{ $couleur === 'primary' || $couleur === 'info' ? 'body' : $couleur }}">{{ $val }}</div>
        <div class="detail">{{ $detail }}</div>
    </div></div></div>
    @endforeach
</div>
<div class="row g-3 mb-4">
    @foreach ([
        ['Validés', $k['valides_periode'], 'circuits menés à terme', 'text-success'],
        ['Rejetés', $k['rejetes_periode'], 'taux de rejet '.$pct($k['taux_rejet_pct']), 'text-danger'],
        ['Retours pour correction', $k['retours_periode'], 'soit '.$pct($k['taux_retour_pct']).' des décisions', 'text-warning'],
        ['Dossiers bloqués', $k['bloques'], 'sans décision depuis '.$data['seuil_blocage_jours'].' j', $k['bloques'] ? 'text-danger' : ''],
        ['Visas émis', $k['visas_emis'], 'empreintes électroniques', ''],
        ['Incidents techniques', array_sum($k['incidents_techniques']), $k['incidents_techniques']['jobs_en_echec'].' tâche(s) de file · '.$k['incidents_techniques']['notifications_en_echec'].' notification(s) · '.$k['incidents_techniques']['decisions_refusees'].' refus tracé(s)', array_sum($k['incidents_techniques']) ? 'text-warning' : ''],
    ] as [$lib, $val, $detail, $cls])
    <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100 wf-kpi"><div class="card-body py-2">
        <div class="libelle">{{ $lib }}</div>
        <div class="valeur {{ $cls }}" style="font-size:1.3rem">{{ $val }}</div>
        <div class="detail">{{ $detail }}</div>
    </div></div></div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    {{-- Évolution des décisions --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-graph-up me-1"></i>Décisions par semaine</div>
            <div class="card-body">
                @if (array_sum($data['evolution']['valide']) + array_sum($data['evolution']['rejete']) + array_sum($data['evolution']['renvoye']) === 0)
                    <div class="text-muted small text-center py-5">Aucune décision sur la période.</div>
                @else
                    <div id="wf-evolution" style="min-height: 260px" role="img" aria-label="Décisions par semaine : validations, rejets et retours"></div>
                @endif
            </div>
        </div>
    </div>
    {{-- Charge par rôle --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-people me-1"></i>Charge en attente par rôle</div>
            <ul class="list-group list-group-flush small">
                @php $maxRole = max(1, collect($data['charge_roles'])->max('ouvertes') ?? 1); @endphp
                @forelse ($data['charge_roles'] as $r)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between"><span>{{ str_replace('_', ' ', $r['role']) }}</span>
                            <span class="wf-num"><strong>{{ $r['ouvertes'] }}</strong>@if($r['en_retard']) · <span class="text-danger">{{ $r['en_retard'] }} en retard</span>@endif</span></div>
                        <div class="progress wf-barre mt-1" role="presentation"><div class="progress-bar {{ $r['en_retard'] ? 'bg-danger' : 'bg-primary' }}" style="width: {{ round($r['ouvertes'] / $maxRole * 100) }}%"></div></div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Aucune étape en attente.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

{{-- Goulots d'étranglement --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-bold"><i class="bi bi-funnel me-1"></i>Goulots d'étranglement par étape <span class="text-muted fw-normal small">— classés par retards, puis par volume en attente</span></div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>Circuit</th><th>Étape</th><th class="text-end">En attente</th><th class="text-end">En retard</th><th class="text-end">Traitées</th>
            <th class="text-end">Délai moyen</th><th class="text-end">Médiane</th><th class="text-end">Dans les délais</th>
        </tr></thead>
        <tbody>
        @forelse ($data['goulots'] as $g)
            <tr class="{{ $g['en_retard'] ? 'table-danger' : '' }}">
                <td><a href="{{ route('workflow.dashboard', $q(['circuit' => $g['circuit']])) }}"><code>{{ $g['circuit'] }}</code></a></td>
                <td class="small">{{ $g['etape'] }}. {{ $g['libelle'] }}<div class="text-muted">{{ str_replace('_', ' ', $g['role']) }}</div></td>
                <td class="text-end wf-num">{{ $g['en_attente'] }}</td>
                <td class="text-end wf-num {{ $g['en_retard'] ? 'text-danger fw-bold' : '' }}">{{ $g['en_retard'] }}</td>
                <td class="text-end wf-num">{{ $g['traitees'] }}</td>
                <td class="text-end wf-num">{{ $duree($g['delai_moyen_heures']) }}</td>
                <td class="text-end wf-num">{{ $duree($g['delai_median_heures']) }}</td>
                <td class="text-end"><span class="badge bg-{{ $couleurSla($g['taux_sla']) }}">{{ $pct($g['taux_sla']) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-3">Aucune étape sur la période.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="row g-3 mb-3">
    {{-- Par circuit --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-3 me-1"></i>Par circuit</div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Circuit</th><th class="text-end">En cours</th><th class="text-end">Retard</th><th class="text-end">Validés</th><th class="text-end">Rejetés</th><th class="text-end">Délai moyen</th><th class="text-end">Délais</th></tr></thead>
                <tbody>
                @php
                    // Circuits sans aucune activité sur la période : regroupés en pied de tableau pour la lisibilité.
                    $actifs = $data['par_circuit']->filter(fn ($w) => $w->en_cours_count + $w->valides_count + $w->rejetes_count > 0);
                    $inactifs = $data['par_circuit']->diff($actifs);
                @endphp
                @foreach ($actifs as $wf)
                    <tr>
                        <td><a href="{{ route('workflow.dashboard', $q(['circuit' => $wf->code])) }}"><code>{{ $wf->code }}</code></a> <span class="text-muted small d-block d-xl-inline">{{ $wf->libelle }}</span></td>
                        <td class="text-end wf-num">{{ $wf->en_cours_count }}</td>
                        <td class="text-end wf-num {{ $wf->en_retard_count ? 'text-danger fw-bold' : '' }}">{{ $wf->en_retard_count }}</td>
                        <td class="text-end wf-num">{{ $wf->valides_count }}</td>
                        <td class="text-end wf-num">{{ $wf->rejetes_count }}@if($wf->taux_rejet)<span class="text-muted small"> ({{ $pct($wf->taux_rejet) }})</span>@endif</td>
                        <td class="text-end wf-num">{{ $duree($wf->delai_moyen_heures) }}</td>
                        <td class="text-end"><span class="badge bg-{{ $couleurSla($wf->taux_sla) }}">{{ $pct($wf->taux_sla) }}</span></td>
                    </tr>
                @endforeach
                @if ($inactifs->isNotEmpty())
                    <tr><td colspan="7" class="small text-muted">Sans activité sur la période : {{ $inactifs->pluck('code')->implode(', ') }}</td></tr>
                @endif
                </tbody>
            </table></div>
        </div>
    </div>
    {{-- Charge par unité --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-building me-1"></i>Charge en attente par unité</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Unité</th><th class="text-end">En attente</th><th class="text-end">En retard</th></tr></thead>
                <tbody>
                @forelse ($data['charge_unites'] as $u)
                    <tr><td><strong>{{ $u['unite'] }}</strong> <span class="text-muted small">{{ \Illuminate\Support\Str::limit($u['libelle'], 40) }}</span></td>
                        <td class="text-end wf-num">{{ $u['ouvertes'] }}</td>
                        <td class="text-end wf-num {{ $u['en_retard'] ? 'text-danger fw-bold' : '' }}">{{ $u['en_retard'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">Aucune étape en attente.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Dossiers en retard --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-alarm me-1"></i>Dossiers en retard <span class="text-muted fw-normal small">— échéance dépassée</span></div>
            <ul class="list-group list-group-flush small">
                @forelse ($data['retards'] as $r)
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <a href="{{ $r['url'] }}" class="fw-semibold">{{ $r['tache']->reference }}</a> · <code>{{ $r['tache']->workflow_code }}</code>
                            <div class="text-muted">{{ \Illuminate\Support\Str::limit($r['libelle'], 70) }}</div>
                            <div class="text-muted">Étape {{ $r['tache']->etape_ordre }} · {{ str_replace('_', ' ', $r['tache']->role) }}@if($r['tache']->structure) · {{ $r['tache']->structure->code }}@endif</div>
                        </div>
                        <div class="text-end text-nowrap">
                            <span class="badge bg-danger">{{ $r['jours'] }} j de retard</span>
                            <div class="text-muted">échéance {{ \App\Support\Horodatage::local($r['tache']->echeance_le, 'd/m/Y') }}</div>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-success"><i class="bi bi-check2-circle me-1"></i>Aucun dossier en retard.</li>
                @endforelse
            </ul>
        </div>
    </div>
    {{-- Dernières actions --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-clock-history me-1"></i>Dernières actions</div>
            <ul class="list-group list-group-flush small">
                @forelse ($data['dernieres_actions'] as $a)
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <span class="badge bg-{{ $couleursAction[$a->action] ?? 'secondary' }}">{{ $libellesAction[$a->action] ?? $a->action }}</span>
                            <code>{{ $a->workflow_code }}</code>@if($a->etape_ordre) · étape {{ $a->etape_ordre }}@endif
                            @if($a->url_objet) · <a href="{{ \App\Services\Notifications\NotificationDispatcher::lienRelatif($a->url_objet) }}">{{ $a->libelle_objet ?? class_basename((string) $a->objet_type).' #'.$a->objet_id }}</a>@endif
                            <div class="text-muted">{{ $a->acteur?->name ?? 'Système' }}@if($a->observation) — « {{ \Illuminate\Support\Str::limit($a->observation, 60) }} »@endif</div>
                        </div>
                        <span class="text-muted text-nowrap">{{ \App\Support\Horodatage::local($a->created_at, 'd/m H:i') }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Aucune action.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/charts.js')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
// Après exécution des modules Vite (ApexCharts) : un module est différé, ce script ne l'est pas.
document.addEventListener('DOMContentLoaded', function () {
    const el = document.querySelector('#wf-evolution');
    if (!el || !window.ApexCharts) { return; }
    const e = @json($data['evolution']);
    new ApexCharts(el, {
        chart: { type: 'bar', height: 260, stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [
            { name: 'Validées', data: e.valide },
            { name: 'Retournées', data: e.renvoye },
            { name: 'Rejetées', data: e.rejete },
        ],
        xaxis: { categories: e.semaines, labels: { rotate: -45, style: { fontSize: '11px' } } },
        yaxis: { labels: { formatter: (v) => Math.round(v) } },
        colors: ['#198754', '#ffc107', '#dc3545'],
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
        dataLabels: { enabled: false },
        legend: { position: 'top' },
        tooltip: { y: { formatter: (v) => v + ' décision(s)' } },
    }).render();
});
</script>
@endpush
