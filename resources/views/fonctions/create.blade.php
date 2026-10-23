@extends('layouts.app')
@section('titre', 'Nouvelle fonction')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('fonctions.store') }}" method="POST">@include('fonctions._form')</form>
</div></div>
@endsection
