{{-- $instance (WorkflowInstance|null), $etC (couleurs statut étape), $objet (dossier, optionnel) --}}
@php
    $objet = $objet ?? $instance?->objet;
    $circuits = $objet ? app(\App\Services\Workflow\CircuitPrevuService::class) : null;
    $prevu = ! $instance && $objet ? $circuits->prevu($objet) : null;
    $amont = $objet ? $circuits->amont($objet) : collect();
    $couleurInstance = ['en_cours' => 'warning', 'valide' => 'success', 'rejete' => 'danger', 'annule' => 'secondary'];
@endphp
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-2 me-1"></i>Circuit de validation
        @if($instance)<span class="badge bg-{{ $couleurInstance[$instance->statut] ?? 'secondary' }} float-end">{{ str_replace('_',' ',$instance->statut) }}</span>
        @elseif($prevu)<span class="badge bg-light text-dark border float-end">non lancé</span>@endif
    </div>
    <div class="card-body">
        @if($instance)
            @if($instance->workflow)<div class="small text-muted mb-2">{{ $instance->workflow->libelle ?? $instance->workflow->code }}</div>@endif
            <ul class="list-unstyled mb-0">
                @foreach($instance->etapes as $e)
                    <li class="d-flex align-items-start mb-3">
                        <span class="badge rounded-pill bg-{{ $etC[$e->statut] ?? 'secondary' }} me-3" style="width:2rem;">{{ $e->ordre }}</span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $e->libelle ?? $e->etapeDefinition?->libelle ?? str_replace('_',' ',$e->role) }}
                                @if($e->ordre === $instance->etape_courante && $instance->statut==='en_cours')<span class="badge bg-warning text-dark">en cours</span>@endif
                            </div>
                            <div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $e->statut)) }}@if($e->acteur) · {{ $e->acteur->name }} · {{ \App\Support\Horodatage::local($e->decision_le, 'd/m/Y H:i') }}@endif</div>
                            @if($e->observation)<div class="small fst-italic text-secondary">« {{ $e->observation }} »</div>@endif
                            @if($e->hash_visa)<div class="small font-monospace text-muted" title="Empreinte visa électronique">Visa {{ substr($e->hash_visa, 0, 12) }}…</div>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @elseif($prevu)
            {{-- Dossier non soumis : circuit qui s'appliquera à la soumission. --}}
            <div class="small text-muted mb-2">Circuit prévu : {{ $prevu->libelle ?? $prevu->code }} — lancé à la soumission du dossier.</div>
            <ul class="list-unstyled mb-0">
                @foreach($prevu->etapes as $e)
                    <li class="d-flex align-items-start mb-3 opacity-75">
                        <span class="badge rounded-pill border text-secondary me-3" style="width:2rem;">{{ $e->ordre }}</span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $e->libelle ?? str_replace('_',' ',$e->role) }}</div>
                            <div class="small text-muted">À venir · {{ str_replace('_', ' ', $e->role) }}@if($e->delai_jours) · délai {{ $e->delai_jours }} jours @endif</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted mb-0">Pas encore soumis à un circuit de validation.</p>
        @endif

        {{-- Circuits déjà accomplis par les dossiers d'origine (chaîne de la dépense). --}}
        @foreach($amont as $a)
            <div class="border-top pt-2 mt-3">
                <div class="small fw-semibold mb-1">
                    <i class="bi bi-arrow-return-right me-1"></i>{{ $a['etape'] }} {{ $a['dossier']->numero }}
                    <span class="badge bg-{{ $couleurInstance[$a['instance']->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$a['instance']->statut) }}</span>
                </div>
                <ul class="list-unstyled small mb-0">
                    @foreach($a['instance']->etapes as $e)
                        <li class="text-muted">
                            <span class="badge rounded-pill bg-{{ $etC[$e->statut] ?? 'secondary' }} me-1">{{ $e->ordre }}</span>
                            {{ str_replace('_',' ',$e->role) }} — {{ str_replace('_', ' ', $e->statut) }}@if($e->acteur) · {{ $e->acteur->name }} · {{ \App\Support\Horodatage::local($e->decision_le, 'd/m/Y H:i') }}@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
