@extends('layouts.app')
@section('titre', 'Incidents')
@php $gc = fn ($g) => \App\Models\Incident::graviteClasse($g); $sc = fn ($s) => \App\Models\Incident::statutClasse($s); @endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Gestion des incidents</h4>
        <span class="text-muted small">Déclaration → Diagnostic → Correction → Validation → Clôture (ITIL)</span>
    </div>
    @can('incident.creer')<a href="{{ route('incidents.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Déclarer un incident</a>@endcan
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold">{{ $synthese['total'] }}</div><div class="text-muted small">Incidents</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-warning">{{ $synthese['ouverts'] }}</div><div class="text-muted small">Ouverts</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-danger">{{ $synthese['critiques_ouverts'] }}</div><div class="text-muted small">Critiques ouverts</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-success">{{ $synthese['clos'] }}</div><div class="text-muted small">Clôturés</div></div></div></div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><select name="statut" class="form-select form-select-sm"><option value="">Tous statuts</option>@foreach ($statuts as $k => $v)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-auto"><select name="gravite" class="form-select form-select-sm"><option value="">Toutes gravités</option>@foreach ($gravites as $k => $v)<option value="{{ $k }}" @selected(request('gravite') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-auto"><select name="categorie" class="form-select form-select-sm"><option value="">Toutes catégories</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('categorie') == $c->id)>{{ $c->libelle }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>N°</th><th>Titre</th><th>Catégorie</th><th>Gravité</th><th>Statut</th><th>Déclarant</th><th>Assigné</th><th class="text-end"></th></tr></thead>
        <tbody>
        @forelse ($incidents as $i)
            <tr>
                <td class="font-monospace small">{{ $i->numero }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($i->titre, 34) }}</td>
                <td class="small">{{ $i->categorie?->libelle ?? '—' }}</td>
                <td><span class="badge bg-{{ $gc($i->gravite) }}">{{ $gravites[$i->gravite] ?? $i->gravite }}</span></td>
                <td><span class="badge bg-{{ $sc($i->statut) }}">{{ $statuts[$i->statut] ?? $i->statut }}</span></td>
                <td class="small">{{ $i->declarant?->name ?? '—' }}</td>
                <td class="small">{{ $i->assigne?->name ?? '—' }}</td>
                <td class="text-end"><a href="{{ route('incidents.show', $i) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">Aucun incident déclaré.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $incidents->links() }}</div>
@endsection
