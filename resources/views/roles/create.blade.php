@extends('layouts.app')
@section('titre', 'Nouveau rôle')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('roles.store') }}" method="POST">@include('roles._form')</form>
</div></div>
@endsection
