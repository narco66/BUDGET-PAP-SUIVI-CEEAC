@extends('layouts.app')
@section('titre', 'Classement documentaire')
@section('contenu')
<h4 class="fw-bold mb-3">Plan de classement documentaire</h4>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Catégories</div>
            <ul class="list-group list-group-flush">
                @foreach($categories as $cat)
                    <li class="list-group-item">
                        <strong>{{ $cat->code }}</strong> — {{ $cat->libelle }}
                        @foreach($cat->enfants as $enf)<div class="ms-3 small text-muted">↳ {{ $enf->libelle }}</div>@endforeach
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Dossiers racine</div>
            <ul class="list-group list-group-flush">
                @foreach($dossiers as $dos)
                    <li class="list-group-item">{{ $dos->reference }} — {{ $dos->titre }}</li>
                @endforeach
            </ul>
        </div>
        <div class="alert alert-info mt-3 small mb-0">Codification : <code>CEEAC/GED/{MODULE}/{TYPE}/{ANNEE}/{NUMERO}</code></div>
    </div>
</div>
@endsection
