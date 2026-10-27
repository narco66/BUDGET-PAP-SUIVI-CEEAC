@extends('layouts.app')
@section('titre', 'Supervision des tâches')
@php $max = fn (array $a) => max(1, ...array_values($a ?: [0])); @endphp

@push('styles')<style>.cd-mt{font-variant-numeric:tabular-nums}.cd-step{border-left:4px solid #0a4a8f !important}</style>@endpush

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-line me-2"></i>Supervision des tâches</h4>
        <span class="text-muted small">Charge, retards et délais de traitement · terminées depuis le {{ \Illuminate\Support\Carbon::parse($s['depuis'])->format('d/m/Y') }}</span>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-1"><input type="date" name="depuis" value="{{ $s['depuis'] }}" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-secondary">Période</button></form>
        <a href="{{ route('taches.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-check me-1"></i>Mes tâches</a>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach ([['Tâches ouvertes', $s['ouvertes'], ''], ['En retard', $s['en_retard'], 'text-danger'], ['Terminées sur la période', $s['terminees_periode'], 'text-success']] as [$l, $v, $c])
    <div class="col-md-4"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2"><div class="fs-5 fw-bold cd-mt {{ $c }}">{{ $v }}</div><div class="text-muted small">{{ $l }}</div></div></div></div>
    @endforeach
</div>

@if (! empty($s['affectations_defaut']))
<div class="alert alert-warning small">
    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Anomalies d'affectation : {{ array_sum($s['affectations_defaut']) }} tâche(s) ouverte(s) sans titulaire organique paramétré (ouvertes par défaut à tous les porteurs du rôle).</div>
    @foreach ($s['affectations_defaut'] as $cle => $n)<span class="badge bg-warning-subtle text-warning-emphasis border me-1">{{ $cle }} : {{ $n }}</span>@endforeach
    <div class="mt-1 text-muted">À corriger dans le référentiel organique : désigner le responsable de l'unité ou y affecter un titulaire du rôle.</div>
</div>
@endif

<div class="row g-3">
    @foreach ([['Charge par module', $s['par_module'], 'bg-primary'], ['Charge par étape (rôle)', $s['par_role'], 'bg-info'], ['Charge par unité initiatrice', $s['par_unite'], 'bg-secondary'], ['Retards par rôle', $s['retards_par_role'], 'bg-danger'], ['Répartition des décisions', $s['decisions'], 'bg-success'], ['Tâches traitées par acteur', $s['par_acteur'], 'bg-warning']] as [$titre, $donnees, $cls])
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white fw-semibold small">{{ $titre }}</div>
        <div class="card-body small">
            @forelse ($donnees as $cle => $n)
                <div class="d-flex justify-content-between"><span>{{ $titre === 'Répartition des décisions' ? (\App\Models\WorkflowTache::DECISIONS[$cle] ?? $cle) : $cle }}</span><span class="cd-mt">{{ $n }}</span></div>
                <div class="progress mb-2" style="height:.4rem"><div class="progress-bar {{ $cls }}" style="width:{{ round($n / $max($donnees) * 100) }}%"></div></div>
            @empty
                <div class="text-muted">Aucune donnée.</div>
            @endforelse
        </div>
    </div></div>
    @endforeach
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-stopwatch me-1"></i>Délais de traitement par étape (période)</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Module · étape</th><th class="text-end">Tâches</th><th class="text-end">Délai moyen (h)</th><th class="text-end">Délai médian (h)</th></tr></thead>
        <tbody>
        @forelse ($s['delais_par_etape'] as $etape => $d)
            <tr><td class="small">{{ $etape }}</td><td class="text-end cd-mt">{{ $d['nombre'] }}</td><td class="text-end cd-mt">{{ $d['moyen_heures'] }}</td><td class="text-end cd-mt">{{ $d['median_heures'] ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-3">Aucune tâche terminée sur la période.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
