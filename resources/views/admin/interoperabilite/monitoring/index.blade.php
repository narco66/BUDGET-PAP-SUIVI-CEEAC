@extends('layouts.app')
@section('titre', 'Monitoring API')

@section('contenu')
<div class="mb-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-graph-up me-2"></i>Monitoring API</h4>
</div>
@include('admin.interoperabilite._nav-module', ['page' => 'monitoring'])

<form method="GET" class="mb-3">
    <select name="jours" class="form-select form-select-sm" style="max-width:200px;" onchange="this.form.submit()">
        @foreach([1 => '24 h', 7 => '7 jours', 30 => '30 jours'] as $v => $label)
            <option value="{{ $v }}" @selected($jours===$v)>{{ $label }}</option>
        @endforeach
    </select>
</form>

<div class="row g-3 mb-4">
    @foreach([
        ['Appels', $kpis['appels_total']],
        ['Succès', $kpis['appels_succes']],
        ['Erreurs', $kpis['appels_erreur']],
        ['Rejets', $kpis['appels_rejet']],
        ['Latence moy. (ms)', $kpis['latence_moy_ms']],
        ['Taux succès %', $kpis['taux_succes']],
    ] as [$label, $val])
    <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">{{ $label }}</div>
        <div class="fs-4 fw-bold">{{ $val }}</div>
    </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm"><div class="card-header bg-white"><strong>Top endpoints</strong></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Chemin</th><th>Appels</th><th>Latence moy.</th></tr></thead>
            <tbody>
            @forelse($topChemins as $row)
                <tr><td class="small"><code>{{ Str::limit($row->chemin, 40) }}</code></td><td>{{ $row->total }}</td><td>{{ (int)$row->latence_moy }} ms</td></tr>
            @empty<tr><td colspan="3" class="text-muted text-center py-3">Aucune donnée</td></tr>@endforelse
            </tbody>
        </table></div></div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm"><div class="card-header bg-white"><strong>Par application</strong></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Application</th><th>Appels</th></tr></thead>
            <tbody>
            @forelse($parApp as $row)
                <tr><td>{{ $row->code }}</td><td>{{ $row->total }}</td></tr>
            @empty<tr><td colspan="2" class="text-muted text-center py-3">Aucune donnée</td></tr>@endforelse
            </tbody>
        </table></div></div>
    </div>
</div>
@endsection
