@extends('layouts.app')
@section('titre', 'Modifier la fonction')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('fonctions.update', $fonction) }}" method="POST">@method('PUT')@include('fonctions._form')</form>
</div></div>
@endsection
