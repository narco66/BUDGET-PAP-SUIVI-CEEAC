@extends('layouts.app')
@section('titre', 'Modifier la ligne budgétaire')
@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('budget.update', $ligne) }}" method="POST">@method('PUT')@include('budget._form')</form>
</div></div>
@if($ligne->montant_engage > 0)
<div class="alert alert-info mt-3 small"><i class="bi bi-info-circle me-1"></i>Consommation : engagé {{ number_format($ligne->montant_engage,0,',',' ') }} · liquidé {{ number_format($ligne->montant_liquide,0,',',' ') }} · payé {{ number_format($ligne->montant_paye,0,',',' ') }} XAF.</div>
@endif
@endsection
