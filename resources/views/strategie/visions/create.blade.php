@extends('layouts.app')
@section('titre', 'Nouvelle vision')
@section('contenu')
@include('strategie._nav', ['active' => 'visions'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.visions.store') }}">@csrf @include('strategie.visions._form')</form></div></div>
@endsection
