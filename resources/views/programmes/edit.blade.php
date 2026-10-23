@extends('layouts.app')
@section('titre', 'Modifier le programme')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('programmes.update', $programme) }}" method="POST">@method('PUT')@include('programmes._form')</form>
</div></div>
@endsection
