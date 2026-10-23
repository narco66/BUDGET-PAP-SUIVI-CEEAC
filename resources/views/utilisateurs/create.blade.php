@extends('layouts.app')
@section('titre', 'Nouvel utilisateur')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('utilisateurs.store') }}" method="POST" enctype="multipart/form-data">@include('utilisateurs._form')</form>
</div></div>
@endsection
