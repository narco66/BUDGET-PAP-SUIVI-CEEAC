{{-- $instance (WorkflowInstance|null), $etC (couleurs statut étape) --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-2 me-1"></i>Circuit de validation
        @if($instance)<span class="badge bg-{{ ['en_cours'=>'warning','valide'=>'success','rejete'=>'danger'][$instance->statut] }} float-end">{{ str_replace('_',' ',$instance->statut) }}</span>@endif
    </div>
    <div class="card-body">
        @if($instance)
            <ul class="list-unstyled mb-0">
                @foreach($instance->etapes as $e)
                    <li class="d-flex align-items-start mb-3">
                        <span class="badge rounded-pill bg-{{ $etC[$e->statut] ?? 'secondary' }} me-3" style="width:2rem;">{{ $e->ordre }}</span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ str_replace('_',' ',$e->role) }}
                                @if($e->ordre === $instance->etape_courante && $instance->statut==='en_cours')<span class="badge bg-warning text-dark">en cours</span>@endif
                            </div>
                            <div class="small text-muted">{{ ucfirst($e->statut) }}@if($e->acteur) · {{ $e->acteur->name }} · {{ $e->decision_le?->format('d/m/Y H:i') }}@endif</div>
            @if($e->observation)<div class="small fst-italic text-secondary">« {{ $e->observation }} »</div>@endif
                            @if($e->hash_visa)<div class="small font-monospace text-muted" title="Empreinte visa électronique">Visa {{ substr($e->hash_visa, 0, 12) }}…</div>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted mb-0">Pas encore soumis à un circuit de validation.</p>
        @endif
    </div>
</div>
