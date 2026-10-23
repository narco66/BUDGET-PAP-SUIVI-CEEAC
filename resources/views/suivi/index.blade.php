@extends('layouts.app')
@section('titre', 'Campagnes de suivi-évaluation')
@section('contenu')
<div class="d-flex justify-content-end mb-3">
    @can('suivi.creer')<a href="{{ route('suivi.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle campagne</a>@endcan
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Libellé</th><th>Exercice</th><th>Type</th><th>Période</th><th class="text-center">Collectes</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse ($campagnes as $c)
            <tr>
                <td>{{ $c->libelle }}</td>
                <td>{{ $c->exercice?->annee }}</td>
                <td class="small">{{ $c->type }}</td>
                <td><code>{{ $c->periode }}</code></td>
                <td class="text-center">{{ $c->collectes_count }}</td>
                <td><span class="badge bg-{{ $c->statut==='ouverte'?'success':'secondary' }}">{{ $c->statut }}</span></td>
                <td class="text-end"><a href="{{ route('suivi.show', $c) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-4">Aucune campagne.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $campagnes->links() }}</div>
@endsection
