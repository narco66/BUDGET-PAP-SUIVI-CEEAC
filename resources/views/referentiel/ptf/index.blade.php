@extends('layouts.app')
@section('titre', 'Partenaires financiers (PTF)')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2 flex-wrap" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Rechercher…" style="width:220px;">
        <select name="type" class="form-select form-select-sm" style="width:180px;" onchange="this.form.submit()">
            <option value="">Tous types</option>
            @foreach (\App\Models\PartenaireFinancier::TYPES as $k=>$v)
                <option value="{{ $k }}" @selected(request('type')===$k)>{{ $v }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    @can('partenaire_financier.creer')
        <a href="{{ route('partenaires.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau PTF</a>
    @endcan
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Code</th><th>Libellé</th><th>Type</th><th>Sources</th><th>Conventions</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($partenaires as $p)
                <tr>
                    <td><code>{{ $p->code }}</code> @if($p->sigle)<span class="badge bg-light text-dark">{{ $p->sigle }}</span>@endif</td>
                    <td>{{ $p->libelle }}</td>
                    <td><span class="badge bg-info-subtle text-info-emphasis">{{ \App\Models\PartenaireFinancier::TYPES[$p->type] ?? $p->type }}</span></td>
                    <td>{{ $p->sources_count }}</td>
                    <td>{{ $p->conventions_count }}</td>
                    <td class="text-center">@if($p->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('partenaires.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        @can('partenaire_financier.modifier')<a href="{{ route('partenaires.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun partenaire financier.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $partenaires->links() }}</div>
@endsection
