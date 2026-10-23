@extends('layouts.app')
@section('titre', 'Plans Annuels de Performance')

@php
$couleurs = ['brouillon'=>'secondary','en_validation'=>'warning','valide'=>'info','publie'=>'success','rejete'=>'danger','clos'=>'dark'];
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Plans Annuels de Performance</h1>
    <div class="d-flex gap-1">
        @can('create', App\Models\Pap::class)
        <a href="{{ route('paps.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouveau PAP</a>
        @endcan
        @can('pap.modifier')
        <a href="{{ route('paps.export.liste') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Exporter</a>
        @endcan
        <a href="{{ route('paps.dashboard') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i>Pilotage</a>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Exercice</th><th>Réf.</th><th>Libellé</th><th>Version</th><th>Statut</th><th>Étape courante</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($paps as $pap)
                <tr>
                    <td><strong>{{ $pap->exercice?->annee }}</strong></td>
                    <td><code>{{ $pap->reference ?? '—' }}</code></td>
                    <td>{{ $pap->libelle }}</td>
                    <td>v{{ $pap->version }}</td>
                    <td><span class="badge bg-{{ $couleurs[$pap->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$pap->statut) }}</span></td>
                    <td class="small text-muted">
                        @if($pap->workflowActif && $pap->workflowActif->statut==='en_cours')
                            Étape {{ $pap->workflowActif->etape_courante }}
                        @else — @endif
                    </td>
                    <td class="text-end"><a href="{{ route('paps.show', $pap) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Ouvrir</a></td>
                </tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-4">Aucun PAP.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $paps->links() }}</div>
@endsection
