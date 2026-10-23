@extends('layouts.app')
@section('titre', 'Types de documents')
@section('contenu')
<h4 class="fw-bold mb-3">Types de documents</h4>
<div class="row g-3">
    <div class="col-md-5">
        <form method="POST" action="{{ route('ged.types.store') }}" class="card border-0 shadow-sm">@csrf
            <div class="card-body">
                <input type="text" name="code" class="form-control form-control-sm mb-2" placeholder="Code" required>
                <input type="text" name="libelle" class="form-control form-control-sm mb-2" placeholder="Libellé" required>
                <select name="categorie_id" class="form-select form-select-sm mb-2"><option value="">Catégorie</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select>
                <button class="btn btn-primary btn-sm">Créer</button>
            </div>
        </form>
    </div>
    <div class="col-md-7">
        <div class="card border-0 shadow-sm table-responsive">
            <table class="table table-sm mb-0"><thead><tr><th>Code</th><th>Libellé</th><th>Catégorie</th></tr></thead>
            <tbody>@foreach($types as $t)<tr><td>{{ $t->code }}</td><td>{{ $t->libelle }}</td><td>{{ $t->categorie?->libelle }}</td></tr>@endforeach</tbody></table>
            <div class="card-footer">{{ $types->links() }}</div>
        </div>
    </div>
</div>
@endsection
