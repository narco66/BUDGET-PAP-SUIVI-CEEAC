@extends('layouts.app')
@section('titre', 'Constructeur de rapports')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('reporting.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Reporting</a>
        <h4 class="fw-bold mb-0">Rapports personnalisés</h4>
        <span class="text-muted small">Construire un export à partir de 5 sources de données institutionnelles</span>
    </div>
    @can('reporting.create')
        <a href="{{ route('reporting.modeles.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau modèle</a>
    @endcan
</div>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>Code</th><th>Titre</th><th>Source</th><th>Colonnes</th><th>Auteur</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse ($modeles as $m)
            <tr>
                <td class="font-monospace small">{{ $m->code }}</td>
                <td>{{ $m->titre }}</td>
                <td class="small">{{ $sources[$m->source]['label'] ?? $m->source }}</td>
                <td class="small">{{ count($m->colonnes ?? []) }} colonne(s)</td>
                <td class="small">{{ $m->auteur?->name ?? '—' }}</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('reporting.modeles.show', $m) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
                    @if(auth()->user()->can('reporting.create') && ($m->cree_par === auth()->id() || auth()->user()->can('reporting.admin')))
                        <a href="{{ route('reporting.modeles.edit', $m) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Aucun modèle personnalisé. Créez votre premier rapport.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $modeles->links() }}</div>
@endsection
