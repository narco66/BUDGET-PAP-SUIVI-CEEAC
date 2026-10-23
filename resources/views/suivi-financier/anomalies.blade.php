@extends('layouts.app')
@section('titre', 'Anomalies budgétaires')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('suivi-financier.index', ['exercice' => $exercice?->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Suivi Financier</a>
        <h1 class="h4 mb-0">Anomalies budgétaires</h1>
    </div>
    @if($exercice)
    <form method="GET">
        <select name="exercice" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
            @foreach($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice->id === $ex->id)>{{ $ex->annee }}</option>
            @endforeach
        </select>
    </form>
    @endif
</div>

@if($audit)
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Écarts compteurs</div><div class="fs-3 fw-bold text-danger">{{ $audit['synthese']['ecarts_compteurs'] ?? 0 }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Anomalies sémantiques</div><div class="fs-3 fw-bold text-warning">{{ $audit['synthese']['anomalies_semantiques'] ?? 0 }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Total</div><div class="fs-3 fw-bold">{{ $audit['total_anomalies'] ?? 0 }}</div>
    </div></div></div>
</div>

@if(!empty($audit['ecarts_compteurs']))
<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-bold">Écarts sur compteurs lignes budgétaires</div>
<div class="table-responsive"><table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Type</th><th>Ligne</th><th>Attendu</th><th>Constaté</th><th>Écart</th></tr></thead>
    <tbody>
    @foreach($audit['ecarts_compteurs'] as $a)
        <tr>
            <td><code>{{ $a['type'] ?? '—' }}</code></td>
            <td>{{ $a['ligne_code'] ?? $a['ligne_id'] ?? '—' }}</td>
            <td>{{ $a['attendu'] ?? '—' }}</td>
            <td>{{ $a['constate'] ?? '—' }}</td>
            <td>{{ $a['ecart'] ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
@endif

@if(!empty($audit['anomalies_semantiques']))
<div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Anomalies sémantiques chaîne dépense</div>
<div class="table-responsive"><table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Type</th><th>Référence</th><th>Détail</th></tr></thead>
    <tbody>
    @foreach($audit['anomalies_semantiques'] as $a)
        <tr>
            <td><code>{{ $a['type'] ?? '—' }}</code></td>
            <td>{{ $a['reference'] ?? '—' }}</td>
            <td class="small">{{ json_encode($a['contexte'] ?? [], JSON_UNESCAPED_UNICODE) }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
@endif

@if(($audit['total_anomalies'] ?? 0) === 0)
<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Aucune anomalie détectée pour cet exercice.</div>
@endif
@else
<div class="alert alert-info">Aucun exercice sélectionné.</div>
@endif
@endsection
