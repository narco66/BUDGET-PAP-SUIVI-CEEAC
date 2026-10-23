@extends('layouts.app')
@section('titre', 'Non-conformités')
@php $gc = fn ($g) => \App\Models\NonConformite::graviteClasse($g); @endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Non-conformités & remédiation</h4>
        <span class="text-muted small">Détection des écarts · plans d'action · suivi de résolution</span>
    </div>
    @can('conformite.creer')<a href="{{ route('conformite.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle non-conformité</a>@endcan
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-success">{{ $synthese['taux_conformite'] }}%</div><div class="text-muted small">Taux de conformité</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold">{{ $synthese['total'] }}</div><div class="text-muted small">Non-conformités</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-warning">{{ $synthese['ouvertes'] }}</div><div class="text-muted small">Ouvertes</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold text-danger">{{ $synthese['critiques'] }}</div><div class="text-muted small">Critiques ouvertes</div></div></div></div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><select name="statut" class="form-select form-select-sm"><option value="">Tous statuts</option>@foreach ($statuts as $k => $v)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-auto"><select name="gravite" class="form-select form-select-sm"><option value="">Toutes gravités</option>@foreach ($gravites as $k => $v)<option value="{{ $k }}" @selected(request('gravite') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-auto"><select name="source" class="form-select form-select-sm"><option value="">Toutes sources</option>@foreach ($sources as $k => $v)<option value="{{ $k }}" @selected(request('source') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th>Source</th><th>Gravité</th><th>Responsable</th><th>Échéance</th><th>Statut</th><th class="text-end">Avt.</th><th></th></tr></thead>
        <tbody>
        @forelse ($nonConformites as $nc)
            <tr>
                <td class="font-monospace small">{{ $nc->code }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($nc->libelle, 34) }}</td>
                <td class="small">{{ $sources[$nc->source] ?? $nc->source }}</td>
                <td><span class="badge bg-{{ $gc($nc->gravite) }}">{{ $gravites[$nc->gravite] ?? $nc->gravite }}</span></td>
                <td class="small">{{ $nc->responsable?->name ?? '—' }}</td>
                <td class="small">{{ $nc->echeance?->format('d/m/Y') ?? '—' }}</td>
                <td class="small">{{ $statuts[$nc->statut] ?? $nc->statut }}</td>
                <td class="text-end small">{{ $nc->taux_avancement }}%</td>
                <td class="text-end"><a href="{{ route('conformite.show', $nc) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty<tr><td colspan="9" class="text-center text-muted py-4">Aucune non-conformité enregistrée.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $nonConformites->links() }}</div>
@endsection
