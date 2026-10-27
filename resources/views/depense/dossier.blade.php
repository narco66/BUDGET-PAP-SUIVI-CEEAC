@extends('layouts.app')
@section('titre', 'Dossier dépense')
@php
$c = ['brouillon'=>'secondary','soumis'=>'warning','valide'=>'success','vise'=>'success','rejete'=>'danger','converti'=>'info','emis'=>'info','execute'=>'success','annule'=>'dark'];
$fmt = fn ($n) => number_format((float)$n, 0, ',', ' ');
$etC = ['en_attente'=>'secondary','valide'=>'success','rejete'=>'danger','renvoye'=>'warning'];
@endphp
@section('contenu')
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-folder2-open me-1"></i>Dossier dépense unifié</h4>
        @if($besoin)<span class="text-muted">Besoin <code>{{ $besoin->numero }}</code></span>@endif
        @if($engagement)<span class="text-muted ms-2">Engagement <code>{{ $engagement->numero }}</code></span>@endif
        @if(!empty($cloture['soldee']))<span class="badge bg-success ms-2">Chaîne soldée</span>@endif
        @if($besoin || $engagement)
            <a href="{{ $besoin ? route('depense.tracabilite.show', ['besoin', $besoin->id]) : route('depense.tracabilite.show', ['engagement', $engagement->id]) }}" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-diagram-3 me-1"></i>Traçabilité intégrale</a>
        @endif
    </div>
    @if($tache)
        <span class="badge bg-light text-dark border">Tâche {{ $tache->code }} · {{ \Illuminate\Support\Str::limit($tache->intitule, 40) }}</span>
    @endif
    @if($priorite)
        <span class="badge bg-warning text-dark">Priorité {{ \App\Models\ExpressionBesoin::PRIORITES[$priorite] ?? $priorite }}</span>
    @endif
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-signpost-split me-1"></i>Suivi de la chaîne : Besoin → Paiement</div>
            <div class="card-body">
                @include('depense._suivi_chaine', ['timeline' => $timeline, 'situation' => $situation, 'cloture' => $cloture ?? null])
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-paperclip me-1"></i>Pièces justificatives</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Étape</th><th>Réf.</th><th>Document</th></tr></thead>
                    <tbody>
                    @forelse ($pieces as $p)
                        <tr>
                            <td>{{ $p['etape'] }} <code class="small">{{ $p['numero'] ?? '' }}</code></td>
                            <td class="small">{{ $p['typologie'] ?? $p['reference'] ?? '—' }}</td>
                            <td class="small">{{ isset($p['piece']) ? $p['piece']->nom : ($p['reference'] ?? '—') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted text-center py-3">Aucune pièce.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    @if($engagement)<a href="{{ route('engagements.show', $engagement) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Engagement</a>@endif
    @if($besoin)<a href="{{ route('besoins.show', $besoin) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Besoin</a>@endif
</div>
@endsection
