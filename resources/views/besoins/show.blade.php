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
                        <a href="{{ route('depense.tracabilite.show', ['besoin', $besoin->id]) }}" class="btn btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Traçabilité</a>
                        <a href="{{ route('besoins.pdf', $besoin) }}" target="_blank" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
                    </div>
                </div>
                <dl class="row mb-0 mt-2">
                    <dt class="col-4">Objet</dt><dd class="col-8">{{ $besoin->objet }}</dd>
                    <dt class="col-4">Structure</dt><dd class="col-8">{{ $besoin->structure?->libelle ?? '—' }}</dd>
                    <dt class="col-4">Demandeur</dt><dd class="col-8">{{ $besoin->demandeur?->name ?? '—' }}</dd>
                    <dt class="col-4">Activité rattachée</dt><dd class="col-8">
                        @if ($besoin->activite)
                            {{ \App\Services\Depense\ImputationPapService::codePap($besoin->activite) }} — {{ $besoin->activite->intitule }}
                        @elseif ($besoin->ligneDetail)
                            {{ $besoin->ligneDetail->code }} — {{ $besoin->ligneDetail->intitule }} <span class="text-muted small">(fonctionnement)</span>
                        @elseif ($besoin->ligneBudgetaire)
                            {{ \App\Services\Depense\ImputationPapService::codeBudget($besoin->ligneBudgetaire) }} — {{ $besoin->ligneBudgetaire->intitule }}
                        @else — @endif
                    </dd>
                    @if ($besoin->activite)
                        <dt class="col-4">Chaîne RBM</dt><dd class="col-8 small">{{ \App\Services\Depense\ImputationPapService::chaineRbm($besoin->activite) ?: '—' }}</dd>
                        <dt class="col-4">Responsable activité</dt><dd class="col-8">{{ $besoin->activite->responsable?->name ?? '—' }}</dd>
                    @endif
                    @if ($besoin->tache)
                        <dt class="col-4">Responsable tâche</dt><dd class="col-8">{{ $besoin->tache->responsable?->name ?? '—' }}</dd>
                    @endif
                    @if ($besoin->tachesActivite->isNotEmpty())
                        <dt class="col-4">Tâches de l'activité</dt><dd class="col-8">
                            <table class="table table-sm small mb-0">
                                @foreach ($besoin->tachesActivite as $t)
                                    <tr><td class="text-nowrap">{{ $t->code }}</td><td>{{ $t->intitule }}</td><td class="text-muted">{{ $t->responsable?->name ?? '—' }}</td><td class="text-end text-nowrap">{{ number_format((float) $t->montant, 0, ',', ' ') }} XAF</td></tr>
                                @endforeach
                            </table>
                        </dd>
                    @endif
                    <dt class="col-4">Imputation</dt><dd class="col-8">{{ $besoin->ligneBudgetaire ? $besoin->ligneBudgetaire->code.' — '.$besoin->ligneBudgetaire->intitule : '—' }}</dd>
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
        @if ($suivi)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-signpost-split me-1"></i>Suivi de la chaîne de dépense</span>
                    <a href="{{ route('depense.dossier.besoin', $besoin) }}" class="small fw-normal">Dossier complet →</a>
                </div>
                <div class="card-body">
                    @include('depense._suivi_chaine', ['timeline' => $suivi['timeline'], 'situation' => $suivi['situation'], 'cloture' => $suivi['cloture'] ?? null, 'compact' => true])
                </div>
            </div>
        @endif
        @include('partials._timeline', ['instance' => $instance, 'etC' => $etC, 'objet' => $besoin])
    </div>
</div>
<div class="mt-3"><a href="{{ route('besoins.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
@endsection
