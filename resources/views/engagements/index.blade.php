@extends('layouts.app')
@section('titre', 'Engagements')
@php $c = ['brouillon'=>'secondary','soumis'=>'warning','vise'=>'success','rejete'=>'danger','annule'=>'dark']; @endphp
@section('contenu')
<div class="d-flex justify-content-end mb-3">
    @can('engagement.creer')<a href="{{ route('engagements.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvel engagement</a>@endcan
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>N°</th><th>Objet</th><th>Type</th><th class="text-end">Montant</th><th>Crédits</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse ($engagements as $e)
            <tr>
                <td><code>{{ $e->numero }}</code></td>
                <td>{{ \Illuminate\Support\Str::limit($e->objet, 45) }}</td>
                <td class="small">{{ str_replace('_',' ',$e->type) }}</td>
                <td class="text-end">{{ number_format($e->montant_total,0,',',' ') }}</td>
                <td>@if($e->credits_reserves)<span class="badge bg-success">réservés</span>@else<span class="badge bg-light text-dark">non réservés</span>@endif</td>
                <td><span class="badge bg-{{ $c[$e->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$e->statut) }}</span></td>
                <td class="text-end"><a href="{{ route('engagements.show', $e) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-4">Aucun engagement.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $engagements->links() }}</div>
@endsection
