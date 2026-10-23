@extends('layouts.app')
@section('titre', 'Modifier l\'expression du besoin')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('besoins.update', $besoin) }}" method="POST">@method('PUT')@include('besoins._form')</form>
</div></div>
@endsection
