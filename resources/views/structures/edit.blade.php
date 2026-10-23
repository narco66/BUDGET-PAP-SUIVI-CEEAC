@extends('layouts.app')
@section('titre', 'Modifier la structure')

@section('contenu')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form action="{{ route('structures.update', $structure) }}" method="POST">
            @method('PUT')
            @include('structures._form')
        </form>
    </div>
</div>
@endsection
