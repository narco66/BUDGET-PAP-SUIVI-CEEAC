@extends('layouts.app')
@section('titre', 'Nouvelle prévision de recette')

@section('contenu')
<div class="mb-3"><a href="{{ route('previsions-recettes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>

<div class="card border-0 shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('previsions-recettes.store') }}">
        @include('recettes.previsions._form')
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
            <a href="{{ route('previsions-recettes.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
</div></div>
@endsection
