@extends('layouts.app')
@section('titre', 'Modifier le poste')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('postes.update', $poste) }}" method="POST">@method('PUT') @include('postes._form')</form>
</div></div>
@endsection
