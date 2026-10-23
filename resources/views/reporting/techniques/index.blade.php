@extends('layouts.app')
@section('titre', 'Rapports techniques')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('reporting.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Reporting</a>
        <h4 class="fw-bold mb-0">Rapports techniques</h4>
        <span class="text-muted small">Supervision plateforme, intégrité des données et journaux d'audit</span>
    </div>
</div>

<form method="GET" class="mb-3">
    <div class="input-group input-group-sm" style="max-width:260px;">
        <span class="input-group-text">Exercice</span>
        <select name="exercice" class="form-select" onchange="this.form.submit()">
            @foreach ($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>{{ $ex->annee }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="row g-3">
    @foreach ($types as $cle => $libelle)
        <div class="col-md-4">
            <a href="{{ route('reporting.show', ['type' => $cle, 'exercice' => $exercice?->id]) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 report-card">
                    <div class="card-body">
                        <i class="bi bi-gear fs-3 text-secondary"></i>
                        <div class="fw-bold mt-2 text-dark">{{ $libelle }}</div>
                        <span class="badge bg-success-subtle text-success mt-2">Disponible</span>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
