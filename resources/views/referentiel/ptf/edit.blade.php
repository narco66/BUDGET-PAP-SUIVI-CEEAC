@extends('layouts.app')
@section('titre', 'Modifier '.$partenaire->code)

@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('partenaires.update', $partenaire) }}">@method('PUT')@include('referentiel.ptf._form')</form>
</div></div>
@endsection
