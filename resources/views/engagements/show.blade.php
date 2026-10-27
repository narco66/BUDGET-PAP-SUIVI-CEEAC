@extends('layouts.app')
@section('titre', 'Engagement · '.$engagement->numero)
@php
$c = ['brouillon'=>'secondary','soumis'=>'warning','vise'=>'success','rejete'=>'danger','annule'=>'dark'];
$etC = ['en_attente'=>'secondary','valide'=>'success','rejete'=>'danger','renvoye'=>'warning'];
$fmt = fn ($n) => number_format((float)$n, 0, ',', ' ');
@endphp
@section('contenu')
<div class="row g-3">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between">
                <h5 class="fw-bold">{{ $engagement->numero }} <span class="badge bg-{{ $c[$engagement->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$engagement->statut) }}</span></h5>
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('depense.dossier.engagement', $engagement) }}" class="btn btn-outline-primary"><i class="bi bi-folder2-open me-1"></i>Dossier</a>
                    <a href="{{ route('depense.tracabilite.show', ['engagement', $engagement->id]) }}" class="btn btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Traçabilité</a>
                    <a href="{{ route('engagements.pdf', $engagement) }}" target="_blank" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Fiche</a>
                    <a href="{{ route('engagements.certificat', $engagement) }}" target="_blank" class="btn btn-outline-danger"><i class="bi bi-patch-check me-1"></i>Certificat</a>
                </div>
            </div>
            <dl class="row mb-0 mt-2">
                <dt class="col-4">Objet</dt><dd class="col-8">{{ $engagement->objet }}</dd>
                <dt class="col-4">Type</dt><dd class="col-8">{{ str_replace('_',' ',$engagement->type) }}</dd>
                <dt class="col-4">Tâche PAP</dt><dd class="col-8">{{ $engagement->tache?->code ?? '—' }} {{ $engagement->tache?->intitule }}</dd>
                <dt class="col-4">Bénéficiaire</dt><dd class="col-8">{{ $engagement->beneficiaire ?? '—' }}</dd>
                <dt class="col-4">Montant total</dt><dd class="col-8 fw-bold">{{ $fmt($engagement->montant_total) }} XAF</dd>
                <dt class="col-4">Crédits</dt><dd class="col-8">@if($engagement->credits_reserves)<span class="badge bg-success">réservés</span>@else<span class="badge bg-secondary">non réservés</span>@endif</dd>
                <dt class="col-4">Établi par</dt><dd class="col-8">{{ $engagement->createur?->name }}</dd>
                <dt class="col-4">Visa CF</dt><dd class="col-8">{{ $engagement->controleur?->name ?? 'En attente' }} {{ $engagement->vise_le?->format('d/m/Y H:i') }}</dd>
            </dl>

            <h6 class="fw-bold mt-3">Imputations</h6>
            <table class="table table-sm">
                <thead class="table-light"><tr><th>Ligne</th><th>Structure</th><th class="text-end">Montant</th><th class="text-end">Dispo. ligne</th></tr></thead>
                <tbody>
                @foreach($engagement->imputations as $imp)
                    <tr>
                        <td><code>{{ $imp->ligneBudgetaire?->code }}</code></td>
                        <td class="small text-muted">{{ $imp->ligneBudgetaire?->structure?->sigle ?? '—' }}</td>
                        <td class="text-end">{{ $fmt($imp->montant) }}</td>
                        <td class="text-end">{{ $fmt($imp->ligneBudgetaire?->disponible) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="d-flex gap-2">
                @can('update', $engagement)
                    @if($engagement->estModifiable())
                    <form action="{{ route('engagements.soumettre', $engagement) }}" method="POST" onsubmit="return confirm('Soumettre ? Les crédits seront réservés.')">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Soumettre (réserver + visa)</button></form>
                    @endif
                @endcan
                @can('annuler', $engagement)
                    <form action="{{ route('engagements.annuler', $engagement) }}" method="POST" onsubmit="return confirm('Annuler et libérer les crédits ?')">@csrf<button class="btn btn-sm btn-outline-dark"><i class="bi bi-x-circle me-1"></i>Annuler</button></form>
                    @if(in_array($engagement->statut, ['vise', 'soumis'], true))
                    <form action="{{ route('engagements.degager', $engagement) }}" method="POST" class="d-flex gap-1" onsubmit="return confirm('Dégager uniquement le reliquat non liquidé ?')">
                        @csrf
                        <input type="number" step="0.01" min="0.01" name="montant" class="form-control form-control-sm" style="width:9rem" required placeholder="Reliquat">
                        <button class="btn btn-sm btn-outline-secondary">Dégager</button>
                    </form>
                    @endif
                @endcan
                @if($engagement->statut === 'vise')
                    @can('liquidation.creer')
                        <a href="{{ route('depense.create', 'liquidation') }}?parent={{ $engagement->id }}" class="btn btn-sm btn-success"><i class="bi bi-arrow-right-circle me-1"></i>Créer la liquidation</a>
                    @endcan
                @endif
            </div>
        </div></div>

        @if($peutAgir)
        @include('partials._workflow_agir', ['action' => route('engagements.agir', $engagement), 'avecRenvoi' => false])
        @endif

        <div class="mt-3">@include('partials._pieces', ['attachable' => $engagement, 'type' => 'engagement'])</div>
        @include('partials._ged_documents', ['attachable' => $engagement, 'type' => 'engagement'])
    </div>

    <div class="col-md-5">
        @include('partials._timeline', ['instance' => $instance, 'etC' => $etC, 'objet' => $engagement])
    </div>
</div>
<div class="mt-3"><a href="{{ route('engagements.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
@endsection
