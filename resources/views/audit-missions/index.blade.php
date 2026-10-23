@extends('layouts.app')
@section('titre', 'Missions d\'audit')
@php $sm = ['planifiee'=>'secondary','en_cours'=>'info','cloturee'=>'success','annulee'=>'dark']; @endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Missions d'audit</h4>
        <span class="text-muted small">Plan annuel · Constats · Recommandations · Suivi (INTOSAI)</span>
    </div>
    @can('audit.creer')<a href="{{ route('audit-missions.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Planifier une mission</a>@endcan
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold">{{ $synthese['total'] }}</div><div class="text-muted small">Missions</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-info">{{ $synthese['ouvertes'] }}</div><div class="text-muted small">Ouvertes</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-success">{{ $synthese['cloturees'] }}</div><div class="text-muted small">Clôturées</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-warning">{{ $synthese['constats_ouverts'] }}</div><div class="text-muted small">Recommandations en attente</div></div></div></div>
</div>

<form method="GET" class="mb-3">
    <select name="statut" class="form-select form-select-sm" style="max-width:220px" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        @foreach ($statuts as $k => $v)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $v }}</option>@endforeach
    </select>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>N°</th><th>Libellé</th><th>Type</th><th>Entité auditée</th><th>Chef de mission</th><th class="text-center">Constats</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($missions as $m)
            <tr>
                <td class="font-monospace small">{{ $m->numero }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($m->libelle, 36) }}</td>
                <td class="small">{{ \App\Models\MissionAudit::TYPES[$m->type] ?? $m->type }}</td>
                <td class="small">{{ $m->structure?->sigle ?? $m->structure?->libelle ?? '—' }}</td>
                <td class="small">{{ $m->chefMission?->name ?? '—' }}</td>
                <td class="text-center"><span class="badge bg-light text-dark">{{ $m->constats_count }}</span>@if($m->constats_ouverts_count) <span class="badge bg-warning">{{ $m->constats_ouverts_count }} ouvert(s)</span>@endif</td>
                <td><span class="badge bg-{{ $sm[$m->statut] ?? 'secondary' }}">{{ $statuts[$m->statut] ?? $m->statut }}</span></td>
                <td class="text-end"><a href="{{ route('audit-missions.show', $m) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">Aucune mission d'audit planifiée.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $missions->links() }}</div>
@endsection
