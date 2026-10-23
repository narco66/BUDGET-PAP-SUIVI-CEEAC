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
    </div>
    @if($tache)
        <span class="badge bg-light text-dark border">Tâche {{ $tache->code }} · {{ \Illuminate\Support\Str::limit($tache->intitule, 40) }}</span>
    @endif
    @if($priorite)
        <span class="badge bg-warning text-dark">Priorité {{ \App\Models\ExpressionBesoin::PRIORITES[$priorite] ?? $priorite }}</span>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-signpost-split me-1"></i>Timeline Besoin → Paiement</div>
            <div class="card-body">
                @forelse ($timeline as $etape)
                    <div class="d-flex mb-3 pb-3 @if(!$loop->last) border-bottom @endif">
                        <div class="me-3">
                            <span class="badge rounded-pill bg-{{ $c[$etape['statut']] ?? 'secondary' }}" style="min-width:5rem;">{{ $etape['label'] }}</span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <strong>
                                    @if($etape['route'])
                                        <a href="{{ $etape['route'] }}">{{ $etape['numero'] }}</a>
                                    @else
                                        {{ $etape['numero'] }}
                                    @endif
                                </strong>
                                <span>{{ $fmt($etape['montant']) }} XAF</span>
                            </div>
                            <div class="small text-muted">
                                Statut : {{ str_replace('_', ' ', $etape['statut']) }}
                                @if($etape['date']) · {{ $etape['date'] instanceof \DateTimeInterface ? $etape['date']->format('d/m/Y') : $etape['date'] }}@endif
                            </div>
                            @if(!empty($etape['services_faits']))
                                <ul class="small mb-0 mt-1">
                                    @foreach($etape['services_faits'] as $sf)
                                        <li>{{ \App\Models\ServiceFait::TYPES[$sf->type] ?? $sf->type }} — {{ $sf->constatation ?? $sf->reference }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Aucune étape enregistrée.</p>
                @endforelse
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

    <div class="col-lg-4">
        @if($besoin && $besoin->workflowActif)
            @include('partials._timeline', ['instance' => $besoin->workflowActif, 'etC' => $etC])
        @elseif($engagement && $engagement->workflowActif)
            @include('partials._timeline', ['instance' => $engagement->workflowActif, 'etC' => $etC])
        @endif
    </div>
</div>

<div class="mt-3">
    @if($engagement)<a href="{{ route('engagements.show', $engagement) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Engagement</a>@endif
    @if($besoin)<a href="{{ route('besoins.show', $besoin) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Besoin</a>@endif
</div>
@endsection
