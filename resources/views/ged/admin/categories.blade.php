@extends('layouts.app')
@section('titre', 'Catégories documentaires')
@section('contenu')
<h4 class="fw-bold mb-3">Catégories documentaires</h4>
<div class="row g-3">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Nouvelle catégorie</div>
            <div class="card-body">
                <form method="POST" action="{{ route('ged.categories.store') }}">@csrf
                    <div class="mb-2"><input type="text" name="code" class="form-control form-control-sm" placeholder="Code" required></div>
                    <div class="mb-2"><input type="text" name="libelle" class="form-control form-control-sm" placeholder="Libellé" required></div>
                    <div class="mb-2"><textarea name="description" class="form-control form-control-sm" placeholder="Description" rows="2"></textarea></div>
                    <button class="btn btn-primary btn-sm">Créer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm mb-0"><thead><tr><th>Code</th><th>Libellé</th><th>Parent</th></tr></thead>
                <tbody>@foreach($categories as $c)<tr><td>{{ $c->code }}</td><td>{{ $c->libelle }}</td><td>{{ $c->parent?->libelle ?? '—' }}</td></tr>@endforeach</tbody></table>
            </div>
            <div class="card-footer">{{ $categories->links() }}</div>
        </div>
    </div>
</div>
@endsection
