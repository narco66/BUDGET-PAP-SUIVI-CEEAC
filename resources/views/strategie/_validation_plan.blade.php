@if(in_array($plan->statut, ['brouillon', 'rejete', 'en_validation', 'actif'], true))
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-check2-circle me-1"></i>Circuit de validation</div>
    <div class="card-body small">
        @if($plan->submitted_at)<p class="mb-1">Soumis le {{ $plan->submitted_at->format('d/m/Y H:i') }}</p>@endif
        @if($plan->validated_at)<p class="mb-1 text-success">Validé le {{ $plan->validated_at->format('d/m/Y H:i') }} par {{ $plan->validateur?->name ?? '—' }}</p>@endif
        @if($plan->motif_rejet)<p class="mb-1 text-danger">Motif rejet : {{ $plan->motif_rejet }}</p>@endif
        <div class="d-flex flex-wrap gap-1 mt-2">
            @can('strategie.update')
                @if(in_array($plan->statut, ['brouillon', 'rejete'], true))
                <form method="POST" action="{{ route('strategie.plans.soumettre', $plan) }}">@csrf<button class="btn btn-sm btn-primary">Soumettre à validation</button></form>
                @endif
            @endcan
            @can('strategie.validate')
                @if($plan->statut === 'en_validation')
                <form method="POST" action="{{ route('strategie.plans.valider', $plan) }}">@csrf<button class="btn btn-sm btn-success">Valider le plan</button></form>
                <form method="POST" action="{{ route('strategie.plans.rejeter', $plan) }}" class="d-flex gap-1">
                    @csrf
                    <input type="text" name="motif_rejet" class="form-control form-control-sm" placeholder="Motif du rejet" style="max-width:220px;">
                    <button class="btn btn-sm btn-outline-danger">Rejeter</button>
                </form>
                @endif
            @endcan
        </div>
    </div>
</div>
@endif
