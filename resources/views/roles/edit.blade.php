@extends('layouts.app')
@section('titre', 'Modifier le rôle')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('roles.update', $role) }}" method="POST">@method('PUT')@include('roles._form')</form>
</div></div>
@endsection
