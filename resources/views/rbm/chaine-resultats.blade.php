@extends('layouts.app')
@section('titre', 'Chaîne de résultats · '.$exercice->annee)

@section('contenu')
@include('rbm._nav-module', ['exercice' => $exercice, 'page' => 'chaine'])

@foreach($chaine as $pilier)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-primary text-white">
        <strong>PILIER {{ $pilier['code'] }}</strong> — {{ $pilier['intitule'] }}
    </div>
    <div class="card-body">
        @foreach($pilier['enfants'] as $axe)
            <div class="mb-3">
                <h3 class="h6 text-success"><i class="bi bi-signpost-split me-1"></i>{{ $axe['code'] }} — {{ $axe['intitule'] }}</h3>
                <ul class="list-group list-group-flush ms-3">
                    @forelse($axe['enfants'] as $act)
                        <li class="list-group-item d-flex justify-content-between py-1 px-0 border-0">
                            <span><code>{{ $act['code'] }}</code> {{ \Illuminate\Support\Str::limit($act['intitule'], 80) }}</span>
                            <span class="text-muted small">{{ number_format($act['montant'], 0, ',', ' ') }} XAF</span>
                        </li>
                    @empty
                        <li class="text-muted small">Aucune activité rattachée.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
</div>
@endforeach
@endsection
