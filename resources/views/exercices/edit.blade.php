@extends('layouts.app')
@section('titre', 'Modifier l\'exercice '.$exercice->code)

@section('contenu')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('exercices.update', $exercice) }}">
            @method('PUT')
            @include('exercices._form')
        </form>
    </div>
</div>
@endsection
