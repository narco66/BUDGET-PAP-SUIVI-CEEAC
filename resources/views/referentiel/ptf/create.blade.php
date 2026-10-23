@extends('layouts.app')
@section('titre', 'Nouveau partenaire financier')

@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('partenaires.store') }}">@include('referentiel.ptf._form')</form>
</div></div>
@endsection
