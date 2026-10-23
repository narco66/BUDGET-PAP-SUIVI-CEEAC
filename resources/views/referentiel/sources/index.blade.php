@extends('layouts.app')
@section('titre', 'Sources de financement')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Rechercher…" style="width:220px;">
        <select name="type" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
            <option value="">Tous types</option>
            @foreach (\App\Models\SourceFinancement::TYPES as $k=>$v)
                <option value="{{ $k }}" @selected(request('type')===$k)>{{ $v }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    @can('source_financement.creer')
        <a href="{{ route('sources.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle source</a>
    @endcan
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Code</th><th>Libellé</th><th>Type</th><th>Classification</th><th>PTF</th><th>Lignes</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($sources as $s)
                <tr>
                    <td><code>{{ $s->code }}</code></td>
                    <td>{{ $s->libelle }}</td>
                    <td><span class="badge {{ $s->type==='interne_ceeac' ? 'bg-primary' : 'bg-info' }}">{{ \App\Models\SourceFinancement::TYPES[$s->type] ?? $s->type }}</span></td>
                    <td class="small">{{ $s->classification ? (\App\Models\SourceFinancement::CLASSIFICATIONS[$s->classification] ?? $s->classification) : '—' }}</td>
                    <td class="small">{{ $s->partenaire?->sigle ?? $s->partenaire?->libelle ?? $s->bailleur ?? '—' }}</td>
                    <td>{{ $s->lignes_budgetaires_count }}</td>
                    <td class="text-end">
                        <a href="{{ route('sources.show', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        @can('source_financement.modifier')<a href="{{ route('sources.edit', $s) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Aucune source.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $sources->links() }}</div>
@endsection
