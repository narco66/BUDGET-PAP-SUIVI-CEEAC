@extends('layouts.app')
@section('titre', 'Modifier le profil')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('profils.update', $profil) }}" method="POST">@method('PUT')@include('profils._form')</form>
</div></div>
@endsection
