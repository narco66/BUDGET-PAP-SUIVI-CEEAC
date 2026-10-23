@extends('layouts.app')
@section('titre', 'Nouveau programme')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('programmes.store') }}" method="POST">@include('programmes._form')</form>
</div></div>
@endsection
