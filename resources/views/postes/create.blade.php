@extends('layouts.app')
@section('titre', 'Nouveau poste')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('postes.store') }}" method="POST">@include('postes._form')</form>
</div></div>
@endsection
