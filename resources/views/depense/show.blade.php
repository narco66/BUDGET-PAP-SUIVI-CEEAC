@extends('layouts.app')
@section('titre', $config['label'].' · '.$item->numero)
@php
$c = ['brouillon'=>'secondary','soumis'=>'warning','valide'=>'success','rejete'=>'danger'];
$etC = ['en_attente'=>'secondary','valide'=>'success','rejete'=>'danger','renvoye'=>'warning'];
$fmt = fn ($n) => number_format((float)$n, 0, ',', ' ');
@endphp
@section('contenu')
<div class="row g-3">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between">
                <h5 class="fw-bold"><i class="bi {{ $config['icone'] }} me-1"></i>{{ $item->numero }} <span class="badge bg-{{ $c[$item->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$item->statut) }}</span></h5>
                <a href="{{ route('depense.tracabilite.show', [$type, $item->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Traçabilité</a>
                <a href="{{ route('depense.pdf', [$type, $item->id]) }}" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            </div>
            <dl class="row mb-0 mt-2">
                <dt class="col-4">Montant</dt>
                <dd class="col-8 fw-bold">
                    {{ $fmt($item->montant) }} {{ $item->devise ?? 'XAF' }}
                    @if($type === 'paiement' && ($item->devise ?? 'XAF') !== 'XAF')
                        <span class="text-muted small">(≈ {{ $fmt($item->montant_xaf ?? $item->montant) }} XAF · taux {{ $item->taux_change }})</span>
                    @endif
                </dd>
                <dt class="col-4">Engagement</dt><dd class="col-8"><code>{{ $engagement->numero }}</code> — {{ $engagement->objet }}</dd>
                <dt class="col-4">Date</dt><dd class="col-8">{{ $item->{$config['date_cle']}?->format('d/m/Y') ?? '—' }}</dd>
                <dt class="col-4">Compteurs</dt><dd class="col-8">@if($item->compteurs_appliques)<span class="badge bg-success">appliqués</span>@else<span class="badge bg-secondary">non appliqués</span>@endif</dd>
                <dt class="col-4">Établi par</dt><dd class="col-8">{{ $item->createur?->name }}</dd>
                @if($item->service_fait)<dt class="col-4">Service fait</dt><dd class="col-8">{{ $item->service_fait }}</dd>@endif
                @if($item->mode_paiement)<dt class="col-4">Mode</dt><dd class="col-8">{{ $item->mode_paiement }} {{ $item->reference_bancaire }}</dd>@endif
            </dl>

            <h6 class="fw-bold mt-3">Lignes imputées (engagement)</h6>
            <table class="table table-sm">
                <thead class="table-light"><tr><th>Ligne</th><th class="text-end">Montant</th><th class="text-end">Engagé</th><th class="text-end">Liquidé</th><th class="text-end">Ordonn.</th><th class="text-end">Payé</th></tr></thead>
                <tbody>
                @foreach($engagement->imputations as $imp)@php $l=$imp->ligneBudgetaire; @endphp
                    <tr>
                        <td><code>{{ $l?->code }}</code></td>
                        <td class="text-end">{{ $fmt($imp->montant) }}</td>
                        <td class="text-end">{{ $fmt($l?->montant_engage) }}</td>
                        <td class="text-end">{{ $fmt($l?->montant_liquide) }}</td>
                        <td class="text-end">{{ $fmt($l?->montant_ordonnance) }}</td>
                        <td class="text-end">{{ $fmt($l?->montant_paye) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="d-flex gap-2">
                @if($item->estModifiable() && auth()->user()->can($config['permission'].'.creer'))
                    <form action="{{ route('depense.soumettre', [$type, $item->id]) }}" method="POST" onsubmit="return confirm('Soumettre à validation ?')">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Soumettre</button></form>
                @endif
                @if($item->statut === 'valide' && $config['enfant_type'] && auth()->user()->can($config['enfant_type'].'.creer'))
                    <a href="{{ route('depense.create', $config['enfant_type']) }}?parent={{ $item->id }}" class="btn btn-sm btn-success"><i class="bi bi-arrow-right-circle me-1"></i>Créer {{ strtolower($config['enfant_label']) }}</a>
                @endif
            </div>
        </div></div>

        @if($peutAgir)
        @include('partials._workflow_agir', ['action' => route('depense.agir', [$type, $item->id]), 'avecRenvoi' => false])
        @endif

        @if($type === 'paiement' && $item->ecrituresComptables?->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3"><div class="card-header bg-white fw-bold"><i class="bi bi-journal-text me-1"></i>Écritures comptables</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>N° pièce</th><th>Débit</th><th>Crédit</th><th class="text-end">Montant XAF</th><th>Statut</th></tr></thead>
                <tbody>
                @foreach($item->ecrituresComptables as $ec)
                    <tr>
                        <td><code>{{ $ec->numero_piece }}</code></td>
                        <td>{{ $ec->compte_debit }}</td>
                        <td>{{ $ec->compte_credit }}</td>
                        <td class="text-end">{{ $fmt($ec->montant) }}</td>
                        <td><span class="badge bg-secondary">{{ $ec->statut }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        @endif

        <div class="mt-3">@include('partials._pieces', ['attachable' => $item, 'type' => $type])</div>
        @include('partials._ged_documents', ['attachable' => $item, 'type' => $type])
    </div>
    <div class="col-md-5">@include('partials._timeline', ['instance' => $instance, 'etC' => $etC, 'objet' => $item])</div>
</div>
<div class="mt-3"><a href="{{ route('depense.index', $type) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
@endsection
