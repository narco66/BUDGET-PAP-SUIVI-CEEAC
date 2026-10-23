@extends('layouts.app')
@section('titre', 'Dossiers documentaires')
@section('contenu')
<h4 class="fw-bold mb-3">Dossiers documentaires</h4>
<div class="row g-3">
    <div class="col-md-4">
        <form method="POST" action="{{ route('ged.dossiers.store') }}" class="card border-0 shadow-sm">@csrf
            <div class="card-body">
                <input type="text" name="reference" class="form-control form-control-sm mb-2" placeholder="Référence" required>
                <input type="text" name="titre" class="form-control form-control-sm mb-2" placeholder="Titre" required>
                <textarea name="description" class="form-control form-control-sm mb-2" placeholder="Description" rows="2"></textarea>
                <button class="btn btn-primary btn-sm">Créer dossier</button>
            </div>
        </form>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <table class="table table-sm mb-0"><thead><tr><th>Réf.</th><th>Titre</th><th>Structure</th></tr></thead>
            <tbody>@foreach($dossiers as $d)<tr><td>{{ $d->reference }}</td><td>{{ $d->titre }}</td><td>{{ $d->structure?->nom ?? '—' }}</td></tr>@endforeach</tbody></table>
            <div class="card-footer">{{ $dossiers->links() }}</div>
        </div>
    </div>
</div>
@endsection
