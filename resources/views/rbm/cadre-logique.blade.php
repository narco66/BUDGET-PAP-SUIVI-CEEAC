@extends('layouts.app')
@section('titre', 'Cadre logique · '.$exercice->annee)

@section('contenu')
@include('rbm._nav-module', ['exercice' => $exercice, 'page' => 'cadre'])

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h2 class="h6 mb-0">{{ $document->intitule ?? 'Cadre logique '.$exercice->annee }}</h2>
        @if(!empty($document->description))
            <p class="small text-muted mb-0">{{ $document->description }}</p>
        @endif
    </div>
    <div class="d-flex flex-wrap gap-1">
        @can('update', \App\Models\CadreLogique::class)
        <a href="{{ route('rbm.cadre-logique.edit', $exercice) }}" class="btn btn-sm btn-warning">
            <i class="bi bi-pencil me-1"></i>Modifier
        </a>
        @endcan
        @canany(['rbm.exporter', 'cadre_logique.voir'])
        <a href="{{ route('rbm.export.cadre-logique', $exercice) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Excel
        </a>
        @endcanany
        @canany(['rbm.generer_pdf', 'cadre_logique.voir'])
        <a href="{{ route('rbm.pdf.cadre-logique', $exercice) }}" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-file-pdf me-1"></i>PDF
        </a>
        @endcanany
    </div>
</div>

@foreach($cadre as $bloc)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white">
        <strong>{{ $bloc['pilier']['code'] }}</strong> — {{ $bloc['pilier']['intitule'] }}
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Axe</th>
                    <th>Objectif stratégique</th>
                    <th>Commentaire</th>
                    <th>Indicateurs</th>
                </tr>
            </thead>
            <tbody>
            @foreach($bloc['objectifs'] as $obj)
                <tr>
                    <td><code>{{ $obj['axe']['code'] }}</code> {{ $obj['axe']['intitule'] }}</td>
                    <td class="small">{{ $obj['objectif_strategique'] ?? '—' }}</td>
                    <td class="small text-muted">{{ $obj['commentaire'] ?? '—' }}</td>
                    <td class="small">
                        @forelse($obj['indicateurs'] as $ind)
                            <span class="badge bg-light text-dark me-1" title="{{ $ind['libelle'] ?? '' }}">{{ $ind['code'] }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach
@endsection
