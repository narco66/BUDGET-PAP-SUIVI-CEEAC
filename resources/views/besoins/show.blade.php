@extends('layouts.app')
@section('titre', 'Besoin · '.$besoin->numero)
@php
$c = ['brouillon'=>'secondary','soumis'=>'warning','valide'=>'success','rejete'=>'danger','converti'=>'info'];
$etC = ['en_attente'=>'secondary','valide'=>'success','rejete'=>'danger','renvoye'=>'warning'];
@endphp
@section('contenu')
<div class="row g-3">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h5 class="fw-bold">{{ $besoin->numero }} <span class="badge bg-{{ $c[$besoin->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$besoin->statut) }}</span></h5>
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('depense.dossier.besoin', $besoin) }}" class="btn btn-outline-primary"><i class="bi bi-folder2-open me-1"></i>Dossier</a>
                        <a href="{{ route('besoins.pdf', $besoin) }}" target="_blank" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
                    </div>
                </div>
                <dl class="row mb-0 mt-2">
                    <dt class="col-4">Objet</dt><dd class="col-8">{{ $besoin->objet }}</dd>
                    <dt class="col-4">Structure</dt><dd class="col-8">{{ $besoin->structure?->libelle ?? '—' }}</dd>
                    <dt class="col-4">Demandeur</dt><dd class="col-8">{{ $besoin->demandeur?->name ?? '—' }}</dd>
                    <dt class="col-4">Activité</dt><dd class="col-8">{{ $besoin->activite?->code ?? '—' }}</dd>
                    <dt class="col-4">Tâche PAP</dt><dd class="col-8">{{ $besoin->tache?->code ?? '—' }} {{ $besoin->tache?->intitule }}</dd>
                    <dt class="col-4">Priorité</dt><dd class="col-8">{{ \App\Models\ExpressionBesoin::PRIORITES[$besoin->priorite] ?? $besoin->priorite }}</dd>
                    <dt class="col-4">Montant estimé</dt><dd class="col-8">{{ number_format($besoin->montant_estime,0,',',' ') }} XAF</dd>
                    <dt class="col-4">Justification</dt><dd class="col-8">{{ $besoin->justification ?? '—' }}</dd>
                </dl>
                <div class="mt-3 d-flex gap-2">
                    @can('update', $besoin)
                        @if($besoin->estModifiable())
                            <a href="{{ route('besoins.edit', $besoin) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>
                            <form action="{{ route('besoins.soumettre', $besoin) }}" method="POST" onsubmit="return confirm('Soumettre pour validation ?')">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Soumettre</button></form>
                        @endif
                    @endcan
                    @if($besoin->statut === 'valide')
                        @can('engagement.creer')
                        <form action="{{ route('besoins.convertir', $besoin) }}" method="POST">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-arrow-right-circle me-1"></i>Convertir en engagement</button></form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

        @if($peutAgir)
        @include('partials._workflow_agir', ['action' => route('besoins.agir', $besoin)])
        @endif

        <div class="mt-3">@include('partials._pieces', ['attachable' => $besoin, 'type' => 'besoin'])</div>
        @include('partials._ged_documents', ['attachable' => $besoin, 'type' => 'besoin'])
    </div>

    <div class="col-md-5">
        @include('partials._timeline', ['instance' => $instance, 'etC' => $etC])
    </div>
</div>
<div class="mt-3"><a href="{{ route('besoins.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
@endsection
