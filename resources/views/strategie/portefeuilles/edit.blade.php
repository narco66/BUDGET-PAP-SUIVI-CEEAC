@extends('layouts.app')
@section('titre', 'Modifier portefeuille')
@section('contenu')
@include('strategie._nav', ['active' => 'portefeuilles'])
<div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('strategie.portefeuilles.update', $portefeuille) }}">@csrf @method('PUT') @include('strategie.portefeuilles._form')</form></div></div>
@endsection
