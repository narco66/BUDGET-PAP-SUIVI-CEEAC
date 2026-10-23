@extends('layouts.app')
@section('titre', 'Nouvelle ligne budgétaire')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('budget.store') }}" method="POST">@include('budget._form')</form>
</div></div>
@endsection
