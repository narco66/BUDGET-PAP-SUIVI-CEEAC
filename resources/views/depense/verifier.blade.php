@extends('layouts.app')
@section('titre', 'Vérification de pièce')
@section('contenu')
<div class="card">
    <div class="card-body">
        <h4 class="h5">Vérification interne</h4>
        <p class="text-muted">Contrôle d’authenticité d’une fiche générée par GEST-BUDGET. Ce n’est pas un portail public.</p>
        <dl class="row mb-0">
            <dt class="col-sm-3">Type</dt><dd class="col-sm-9">{{ $type }}</dd>
            <dt class="col-sm-3">Numéro</dt><dd class="col-sm-9 font-monospace">{{ $objet->numero ?? $objet->id }}</dd>
            <dt class="col-sm-3">Statut</dt><dd class="col-sm-9">{{ $objet->statut ?? '—' }}</dd>
            <dt class="col-sm-3">Empreinte</dt><dd class="col-sm-9 font-monospace small">{{ $empreinte }}</dd>
        </dl>
    </div>
</div>
@endsection
