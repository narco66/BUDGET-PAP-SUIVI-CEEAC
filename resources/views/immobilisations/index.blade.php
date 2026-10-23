@extends('layouts.app')
@section('titre', 'Immobilisations & inventaire')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Registre des immobilisations</h4>
    @can('immobilisation.creer')
        <a href="{{ route('immobilisations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau bien</a>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Tous exercices</option>
            @foreach ($exercices as $ex)
                <option value="{{ $ex->id }}" @selected(request('exercice') == $ex->id)>{{ $ex->annee }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="categorie" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Toutes catégories</option>
            @foreach ($categories as $k => $v)
                <option value="{{ $k }}" @selected(request('categorie') === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            @foreach ($statuts as $k => $v)
                <option value="{{ $k }}" @selected(request('statut') === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>N°</th><th>Désignation</th><th>Catégorie</th><th>Structure</th>
            <th class="text-end">Valeur</th><th class="text-end">VNC</th><th class="text-center">Statut</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse ($immobilisations as $i)
            <tr>
                <td class="font-monospace small">{{ $i->numero }}</td>
                <td>{{ \Illuminate\Support\Str::limit($i->designation, 40) }}</td>
                <td class="small">{{ \App\Models\Immobilisation::CATEGORIES[$i->categorie] ?? $i->categorie }}</td>
                <td class="small">{{ $i->structure?->sigle ?? $i->structure?->libelle ?? '—' }}</td>
                <td class="text-end">{{ number_format($i->valeur_acquisition, 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($i->valeurNette(), 0, ',', ' ') }}</td>
                <td class="text-center">
                    <span class="badge bg-{{ $i->statut === 'en_service' ? 'success' : ($i->statut === 'cede' ? 'secondary' : 'warning') }}">
                        {{ \App\Models\Immobilisation::STATUTS[$i->statut] ?? $i->statut }}
                    </span>
                </td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('immobilisations.show', $i) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
                    @can('immobilisation.modifier')
                        <a href="{{ route('immobilisations.edit', $i) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>
                    @endcan
                    @can('immobilisation.supprimer')
                        <form action="{{ route('immobilisations.destroy', $i) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
                        </form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Aucune immobilisation enregistrée.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $immobilisations->links() }}</div>
@endsection
