@extends('layouts.app')
@section('titre', 'Modifier l\'utilisateur')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('utilisateurs.update', $utilisateur) }}" method="POST" enctype="multipart/form-data">@method('PUT')@include('utilisateurs._form')</form>
</div></div>
@endsection
