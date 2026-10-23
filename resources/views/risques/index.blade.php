@extends('layouts.app')
@section('titre', 'Registre des risques')
@php $meta = fn ($n) => \App\Models\Risque::niveauMeta($n); @endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Registre des risques</h4>
        <span class="text-muted small">Criticité = Probabilité × Impact (ISO 31000 / COSO)</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('risques.cartographie') }}" class="btn btn-outline-info btn-sm"><i class="bi bi-grid-3x3-gap me-1"></i>Cartographie</a>
        @can('risques.exporter')
        <div class="btn-group">
            <a href="{{ route('risques.export', 'pdf') }}" class="btn btn-outline-success btn-sm" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
            <a href="{{ route('risques.export', 'xlsx') }}" class="btn btn-outline-success btn-sm" title="Excel"><i class="bi bi-file-earmark-excel"></i></a>
            <a href="{{ route('risques.export', 'csv') }}" class="btn btn-outline-success btn-sm" title="CSV"><i class="bi bi-filetype-csv"></i></a>
        </div>
        @endcan
        @can('risques.creer')<a href="{{ route('risques.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau risque</a>@endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold">{{ $stats['total'] }}</div><div class="text-muted small">Risques (registre + existants)</div></div></div></div>
    @foreach (['critique', 'eleve', 'modere', 'faible'] as $niv)
        <div class="col-6 col-lg-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="fs-5 fw-bold text-{{ $meta($niv)['classe'] }}">{{ $stats['par_niveau'][$niv] ?? 0 }}</div>
            <div class="text-muted small">{{ $meta($niv)['libelle'] }}</div></div></div></div>
    @endforeach
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Code ou libellé…"></div>
    <div class="col-auto"><select name="categorie" class="form-select form-select-sm">
        <option value="">Toutes catégories</option>
        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('categorie') == $c->id)>{{ $c->libelle }}</option>@endforeach
    </select></div>
    <div class="col-auto"><select name="niveau" class="form-select form-select-sm">
        <option value="">Tous niveaux</option>
        @foreach (['critique' => 'Critique', 'eleve' => 'Élevé', 'modere' => 'Modéré', 'faible' => 'Faible'] as $k => $v)<option value="{{ $k }}" @selected(request('niveau') === $k)>{{ $v }}</option>@endforeach
    </select></div>
    <div class="col-auto"><select name="statut" class="form-select form-select-sm">
        <option value="">Tous statuts</option>
        @foreach ($statuts as $k => $v)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $v }}</option>@endforeach
    </select></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Catégorie</th><th>Libellé</th><th>Propriétaire</th>
            <th class="text-center">P</th><th class="text-center">I</th><th class="text-center">Criticité</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($risques as $r)
            <tr>
                <td class="font-monospace small">{{ $r->code }}</td>
                <td class="small">{{ $r->categorie?->libelle ?? '—' }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($r->libelle, 40) }}</td>
                <td class="small">{{ $r->proprietaire?->name ?? '—' }}</td>
                <td class="text-center small">{{ $r->probabilite }}</td>
                <td class="text-center small">{{ $r->impact }}</td>
                <td class="text-center"><span class="badge bg-{{ $meta($r->niveau)['classe'] }}">{{ $r->criticite }} · {{ $meta($r->niveau)['libelle'] }}</span></td>
                <td class="small">{{ $statuts[$r->statut] ?? $r->statut }}</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('risques.show', $r) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
                    @can('risques.modifier')<a href="{{ route('risques.edit', $r) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>@endcan
                    @can('risques.supprimer')<form method="POST" action="{{ route('risques.archiver', $r) }}" class="d-inline" onsubmit="return confirm('Archiver ce risque ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-archive"></i></button></form>@endcan
                </td>
            </tr>
        @empty<tr><td colspan="9" class="text-center text-muted py-4">Aucun risque enregistré.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $risques->links() }}</div>
@endsection
