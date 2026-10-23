@extends('layouts.app')
@section('titre', 'Suivi des indicateurs IOV')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">Suivi des indicateurs IOV</h1>
        <p class="text-muted small mb-0">Pilotage de la performance · mesures validées · tendances N/N-1</p>
    </div>
    @if($exercice)
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="small text-muted mb-0">Exercice</label>
        <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()" style="width:120px;">
            @foreach($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>{{ $ex->annee }}</option>
            @endforeach
        </select>
    </form>
    @endif
</div>

@if($exercice)
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Feux verts</div><div class="fs-3 fw-bold text-success">{{ $feux['vert'] }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Feux orange</div><div class="fs-3 fw-bold text-warning">{{ $feux['orange'] }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Feux rouges</div><div class="fs-3 fw-bold text-danger">{{ $feux['rouge'] }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Snapshots IOV</div><div class="fs-3 fw-bold text-info">{{ $performances->count() }}</div>
        <small class="text-muted">performances_indicateurs</small>
    </div></div></div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('rbm.arbre', $exercice) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Arbre RBM</a>
    @can('rapport.voir')
    <a href="{{ route('reporting.show', ['type' => 'indicateurs_evolution', 'exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-graph-up me-1"></i>Rapport évolution</a>
    @endcan
</div>

@if($alertes->isNotEmpty())
<div class="card border-0 shadow-sm mb-3 border-start border-3 border-danger">
    <div class="card-header bg-white fw-bold text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Alertes (feu rouge)</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Indicateur</th><th>Période</th><th>Taux</th></tr></thead>
            <tbody>
            @foreach($alertes as $a)
                <tr>
                    <td><code>{{ $a['code'] }}</code></td>
                    <td>{{ $a['libelle'] }}</td>
                    <td>{{ $a['derniere_periode'] ?? '—' }}</td>
                    <td><span class="badge bg-danger">{{ $a['taux_atteinte'] }}%</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold">Synthèse IOV (dernière mesure validée)</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Code</th><th>Libellé</th><th>Période</th><th>Cible</th><th>Réalisé</th>
                    <th>Écart</th><th>Taux</th><th>Feu</th><th>Tendance</th>
                </tr>
            </thead>
            <tbody>
            @forelse($evolution as $row)
                <tr>
                    <td><code>{{ $row['code'] }}</code></td>
                    <td>{{ \Illuminate\Support\Str::limit($row['libelle'], 40) }}</td>
                    <td>{{ $row['derniere_periode'] ?? '—' }}</td>
                    <td>{{ $row['cible'] ?: '—' }}</td>
                    <td>{{ $row['valeur_realisee'] ?: '—' }}</td>
                    <td>{{ $row['ecart_cible'] }}</td>
                    <td>{{ $row['taux_atteinte'] }}%</td>
                    <td>
                        @if(($row['feu'] ?? '') === 'vert')<span class="badge bg-success">Vert</span>
                        @elseif(($row['feu'] ?? '') === 'orange')<span class="badge bg-warning text-dark">Orange</span>
                        @elseif($row['feu'])<span class="badge bg-danger">Rouge</span>
                        @else<span class="text-muted">—</span>@endif
                    </td>
                    <td>
                        @if($row['variation_pct'] !== null)
                            {{ $row['variation_pct'] > 0 ? '+' : '' }}{{ $row['variation_pct'] }}%
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-3">Aucun indicateur IOV sur cet exercice.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@else
<div class="alert alert-info">Aucun exercice disponible.</div>
@endif
@endsection
