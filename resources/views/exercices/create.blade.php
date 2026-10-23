@extends('layouts.app')
@section('titre', 'Nouvel exercice budgétaire')

@section('contenu')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('exercices.store') }}">
            @include('exercices._form')
        </form>
    </div>
</div>
@endsection
