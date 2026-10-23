@extends('layouts.app')
@section('titre', 'Créer un PAP')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Nouveau Plan Annuel de Performance</h1>
    <a href="{{ route('paps.index') }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

@if($exercices->isEmpty())
<div class="alert alert-warning">Tous les exercices disposent déjà d'un PAP.</div>
@else
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form action="{{ route('paps.store') }}" method="POST">
            @csrf
            @include('paps._form')
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Créer le PAP</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
