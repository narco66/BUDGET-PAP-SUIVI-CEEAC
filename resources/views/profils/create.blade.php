@extends('layouts.app')
@section('titre', 'Nouveau profil')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('profils.store') }}" method="POST">@include('profils._form')</form>
</div></div>
@endsection
