@extends('layouts.app')
@section('titre', 'Nouvelle structure')

@section('contenu')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form action="{{ route('structures.store') }}" method="POST">
            @include('structures._form')
        </form>
    </div>
</div>
@endsection
