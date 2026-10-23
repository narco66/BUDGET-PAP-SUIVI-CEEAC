@extends('layouts.app')
@section('titre', 'Suivi Financier')

@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $b = $data['budget'] ?? [];
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">Suivi Financier</h1>
        <p class="text-muted small mb-0">Pilotage budgétaire · chaîne de la dépense · écarts PAP</p>
    </div>
    @if($exercice)
    <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="exercice" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
            @foreach($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice->id === $ex->id)>{{ $ex->annee }}</option>
            @endforeach
        </select>
    </form>
    @endif
</div>

@if($exercice && $data)
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Taux d'engagement</div><div class="fs-4 fw-bold text-warning">{{ $b['taux_engagement'] ?? 0 }}%</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Taux d'exécution</div><div class="fs-4 fw-bold text-success">{{ $b['taux_execution'] ?? 0 }}%</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm border-start border-3 border-warning"><div class="card-body">
        <div class="text-muted small">Restes à engager</div><div class="fs-5 fw-bold">{{ $fmt($b['reste_a_engager'] ?? 0) }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm border-start border-3 border-danger"><div class="card-body">
        <div class="text-muted small">Restes à payer</div><div class="fs-5 fw-bold">{{ $fmt($b['reste_a_payer'] ?? 0) }}</div>
    </div></div></div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    @canany(['suivi_financier.export', 'rapport.exporter', 'depenses.export'])
    <a href="{{ route('depense.export.restes-engager', ['exercice' => $exercice->annee]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Restes à engager</a>
    <a href="{{ route('depense.export.restes', ['exercice' => $exercice->annee]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Restes à payer</a>
    @endcanany
    @can('depenses.export')
    <a href="{{ route('depense.export.archiver', ['cle' => 'restes-a-engager', 'exercice' => $exercice->annee]) }}" class="btn btn-sm btn-outline-primary" title="Archiver une version officielle traçable"><i class="bi bi-shield-check me-1"></i>Archiver restes à engager</a>
    <a href="{{ route('depense.export.archiver', ['cle' => 'restes-a-payer', 'exercice' => $exercice->annee]) }}" class="btn btn-sm btn-outline-primary" title="Archiver une version officielle traçable"><i class="bi bi-shield-check me-1"></i>Archiver restes à payer</a>
    @endcan
    @can('rapport.voir')
    <a href="{{ route('reporting.show', ['type' => 'situation_budgetaire', 'exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-primary">Situation budgétaire</a>
    <a href="{{ route('reporting.show', ['type' => 'ecarts_physique_financier', 'exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-primary">Écarts phys./fin.</a>
    @endcan
    @canany(['suivi_financier.validate', 'suivi_financier.admin'])
    <form method="POST" action="{{ route('suivi-financier.situation.capture') }}" class="d-inline">@csrf
        <input type="hidden" name="exercice" value="{{ $exercice->id }}">
        <button class="btn btn-sm btn-primary"><i class="bi bi-camera me-1"></i>Capturer situation</button>
    </form>
    @endcanany
    <a href="{{ route('suivi-financier.anomalies', ['exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-danger">
        Anomalies ({{ $data['anomalies_total'] }})
    </a>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-chaine" type="button">Chaîne</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-structure" type="button">Structures</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ecarts" type="button">Écarts PAP</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-taches" type="button">Tâches</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-chaine">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="row text-center g-2">
                @foreach(['ouvert'=>'Ouvert','engage'=>'Engagé','liquide'=>'Liquidé','ordonnance'=>'Ordonnancé','paye'=>'Payé'] as $k=>$label)
                <div class="col"><div class="small text-muted">{{ $label }}</div><div class="fw-bold">{{ $fmt($b[$k] ?? 0) }}</div></div>
                @endforeach
            </div>
        </div></div>
    </div>
    <div class="tab-pane fade" id="tab-structure">
        <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Structure</th><th class="text-end">Ouvert</th><th class="text-end">Engagé</th><th class="text-end">Payé</th></tr></thead>
            <tbody>
            @foreach($data['parStructure'] as $s)
                <tr><td>{{ $s['libelle'] }}</td><td class="text-end">{{ $fmt($s['ouvert']) }}</td><td class="text-end">{{ $fmt($s['engage']) }}</td><td class="text-end">{{ $fmt($s['paye']) }}</td></tr>
            @endforeach
            </tbody>
        </table></div></div>
    </div>
    <div class="tab-pane fade" id="tab-ecarts">
        <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Activité</th><th class="text-end">Phys.</th><th class="text-end">Fin.</th><th class="text-end">Écart</th><th>Type</th></tr></thead>
            <tbody>
            @foreach($data['ecarts']->take(20) as $e)
                <tr>
                    <td><code>{{ $e['code'] }}</code> {{ \Illuminate\Support\Str::limit($e['intitule'], 35) }}</td>
                    <td class="text-end">{{ $e['taux_physique'] }}%</td>
                    <td class="text-end">{{ $e['taux_financier'] }}%</td>
                    <td class="text-end">{{ $e['ecart'] }}%</td>
                    <td><span class="badge bg-{{ $e['type_ecart'] === 'alignée' ? 'success' : 'warning' }}">{{ $e['type_ecart'] }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
    </div>
    <div class="tab-pane fade" id="tab-taches">
        <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Tâche</th><th class="text-end">Engagé</th><th class="text-end">Liquidé</th><th class="text-end">Ordonnancé</th><th class="text-end">Payé</th></tr></thead>
            <tbody>
            @forelse($data['parTache'] as $t)
                <tr>
                    <td><code>{{ $t->tache_code }}</code> {{ $t->tache_intitule }}</td>
                    <td class="text-end">{{ $fmt($t->montant_engage) }}</td>
                    <td class="text-end">{{ $fmt($t->montant_liquide) }}</td>
                    <td class="text-end">{{ $fmt($t->montant_ordonnance) }}</td>
                    <td class="text-end">{{ $fmt($t->montant_paye) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">Aucune tâche avec mouvements.</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
</div>
@else
<div class="alert alert-info">Sélectionnez un exercice pour afficher le suivi financier.</div>
@endif
@endsection
