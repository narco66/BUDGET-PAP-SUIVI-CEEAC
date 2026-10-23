@extends('layouts.app')
@section('titre', 'Tableau de bord institutionnel')

@section('contenu')
@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Tableau de bord institutionnel</h4>
        <span class="text-muted small">Effectifs, budget, PAP, execution et performance par structure.</span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        @include('structures._export_buttons')
        <a href="{{ route('organigramme') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-diagram-3 me-1"></i>Organigramme</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Departements</div><div class="fs-4 fw-bold">{{ $indicateurs['effectifs_departements']->count() }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Directions</div><div class="fs-4 fw-bold">{{ $indicateurs['effectifs_directions']->count() }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Services</div><div class="fs-4 fw-bold">{{ $indicateurs['effectifs_services']->count() }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Budget total</div><div class="fs-4 fw-bold">{{ $money($indicateurs['budget_departements']->sum('alloue')) }}</div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Execution budgetaire par departement</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Departement</th><th class="text-end">Alloue</th><th class="text-end">Engage</th><th class="text-end">Paye</th><th class="text-end">Execution</th><th class="text-end">Performance</th></tr></thead>
            <tbody>
            @forelse($indicateurs['execution_departements'] as $row)
                <tr>
                    <td><span class="fw-semibold">{{ $row->code }}</span> - {{ $row->libelle }}</td>
                    <td class="text-end">{{ $money($row->alloue) }}</td>
                    <td class="text-end">{{ $money($row->engage) }}</td>
                    <td class="text-end">{{ $money($row->paye) }}</td>
                    <td class="text-end">{{ $row->taux_execution }}%</td>
                    <td class="text-end">{{ $row->performance }}%</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Aucune donnee budgetaire.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Effectifs par departement</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Departement</th><th class="text-end">Effectif</th></tr></thead>
                    <tbody>
                    @foreach($indicateurs['effectifs_departements'] as $row)
                        <tr><td>{{ $row->code }} - {{ $row->libelle }}</td><td class="text-end">{{ $row->effectif }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">PAP par departement</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Departement</th><th class="text-end">Activites</th><th class="text-end">Physique</th><th class="text-end">Financier</th></tr></thead>
                    <tbody>
                    @forelse($indicateurs['pap_departements'] as $row)
                        <tr><td>{{ $row->code }} - {{ $row->libelle }}</td><td class="text-end">{{ $row->activites }}</td><td class="text-end">{{ round($row->taux_physique, 1) }}%</td><td class="text-end">{{ round($row->taux_financier, 1) }}%</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Aucune activite PAP.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
