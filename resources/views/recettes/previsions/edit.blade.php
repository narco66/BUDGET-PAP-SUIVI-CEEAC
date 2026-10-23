@extends('layouts.app')
@section('titre', 'Modifier la prévision '.$prevision->reference)

@section('contenu')
<div class="mb-3"><a href="{{ route('previsions-recettes.show', $prevision) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>

<div class="card border-0 shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('previsions-recettes.update', $prevision) }}">
        @method('PUT')
        @include('recettes.previsions._form')
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Mettre à jour</button>
            <a href="{{ route('previsions-recettes.show', $prevision) }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
</div></div>
@endsection
