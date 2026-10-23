@extends('layouts.app')
@section('titre', 'Conservation numérique')
@section('contenu')
<h4 class="fw-bold mb-3">Règles de conservation</h4>
<div class="card border-0 shadow-sm table-responsive">
    <table class="table table-sm mb-0"><thead><tr><th>Type / Catégorie</th><th>Durée (ans)</th><th>Sort final</th><th>Alerte (j)</th></tr></thead>
    <tbody>@foreach($regles as $r)<tr>
        <td>{{ $r->typeDocument?->libelle ?? $r->categorie?->libelle ?? '—' }}</td>
        <td>{{ $r->duree_annees ?? 'Permanent' }}</td>
        <td>{{ $r->sort_final }}</td>
        <td>{{ $r->alerte_jours_avant }}</td>
    </tr>@endforeach</tbody></table>
    <div class="card-footer">{{ $regles->links() }}</div>
</div>
@endsection
