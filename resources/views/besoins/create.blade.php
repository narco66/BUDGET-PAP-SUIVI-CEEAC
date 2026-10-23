@extends('layouts.app')
@section('titre', 'Nouvelle expression du besoin')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('besoins.store') }}" method="POST">@include('besoins._form')</form>
</div></div>
@endsection
