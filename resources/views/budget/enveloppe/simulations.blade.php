@extends('layouts.app')
@section('titre', 'Simulations · '.$budget->reference)

@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">Consolidation & simulations</h5>
        <small class="text-muted">{{ $budget->reference }} · Exercice {{ $budget->exercice?->annee }}</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('budget.enveloppe.preparations', $budget) }}" class="btn btn-sm btn-outline-success">Préparation</a>
        <a href="{{ route('budget.enveloppe.show', $budget) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Enveloppe</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Historique consolidations</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Version</th><th class="text-end">Lignes</th><th class="text-end">Total</th><th>Date</th></tr></thead>
                    <tbody>
                    @forelse($consolidations as $c)
                        <tr>
                            <td><code>v{{ $c->version }}</code></td>
                            <td class="text-end">{{ $c->lignes_count }}</td>
                            <td class="text-end fw-semibold">{{ $fmt($c->montant_total) }}</td>
                            <td class="small text-muted">{{ $c->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Aucune consolidation enregistrée.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('simuler', $budget)
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Nouveau scénario</div>
            <div class="card-body">
                <form action="{{ route('budget.enveloppe.simulations.store', $budget) }}" method="POST">@csrf
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small">Libellé</label>
                            <input type="text" name="libelle" class="form-control form-control-sm" required maxlength="200" placeholder="Ex. Réduction 5 % globale">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Type</label>
                            <select name="type" class="form-select form-select-sm" id="sim-type" required>
                                <option value="global_pct">Variation globale (%)</option>
                                <option value="structure_pct">Variation par structure (%)</option>
                                <option value="enveloppe_plafond">Plafond enveloppe</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Hypothèse</label>
                            <textarea name="hypothese" class="form-control form-control-sm" rows="2" placeholder="Contexte du scénario"></textarea>
                        </div>
                        <div class="col-md-4" id="field-taux">
                            <label class="form-label small">Taux (%)</label>
                            <input type="number" name="taux" class="form-control form-control-sm" step="0.01" value="0">
                        </div>
                        <div class="col-md-4 d-none" id="field-structure">
                            <label class="form-label small">Structure</label>
                            <select name="structure_id" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach(\App\Models\Structure::actif()->orderBy('libelle')->get(['id','sigle','libelle']) as $s)
                                <option value="{{ $s->id }}">{{ $s->sigle ?? $s->libelle }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-none" id="field-plafond">
                            <label class="form-label small">Plafond (FCFA)</label>
                            <input type="number" name="plafond" class="form-control form-control-sm" min="0" step="1">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-sm btn-primary"><i class="bi bi-calculator me-1"></i>Créer et calculer</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span>Scénarios comparables</span>
        @if($simulations->whereIn('statut', ['calcule','retenu'])->count() >= 2)
        <form action="{{ route('budget.enveloppe.simulations.comparer', $budget) }}" method="POST" class="d-flex gap-2 align-items-center">@csrf
            @foreach($simulations->whereIn('statut', ['calcule','retenu']) as $s)
            <label class="small mb-0"><input type="checkbox" name="simulations[]" value="{{ $s->id }}" class="form-check-input me-1" {{ in_array($s->id, $ids ?? []) ? 'checked' : '' }}>{{ $s->code }}</label>
            @endforeach
            <button class="btn btn-sm btn-outline-primary">Comparer</button>
        </form>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Code</th><th>Libellé</th><th>Type</th><th class="text-end">Base</th><th class="text-end">Simulé</th><th class="text-end">Écart</th><th>Statut</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($simulations as $s)
                <tr class="{{ $s->statut === 'retenu' ? 'table-success' : '' }}">
                    <td><code>{{ $s->code }}</code></td>
                    <td>{{ $s->libelle }}</td>
                    <td class="small">{{ \App\Models\BudgetSimulation::TYPES[$s->type] ?? $s->type }}</td>
                    <td class="text-end">{{ $fmt($s->montant_base) }}</td>
                    <td class="text-end fw-semibold">{{ $s->estCalculee() ? $fmt($s->montant_simule) : '—' }}</td>
                    <td class="text-end {{ $s->ecart_absolu < 0 ? 'text-danger' : 'text-success' }}">
                        @if($s->estCalculee()){{ $fmt($s->ecart_absolu) }} ({{ number_format($s->ecart_pct, 2) }} %)@else—@endif
                    </td>
                    <td><span class="badge bg-light text-dark">{{ $s->statut }}</span></td>
                    <td class="text-end">
                        @can('simuler', $budget)
                        @if($s->statut === 'calcule')
                        <form action="{{ route('budget.enveloppe.simulations.retenir', [$budget, $s]) }}" method="POST" class="d-inline">@csrf
                            <button class="btn btn-sm btn-outline-success" title="Retenir"><i class="bi bi-bookmark-check"></i></button>
                        </form>
                        @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Aucun scénario — créez un scénario what-if ci-dessus.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($comparaison)
<div class="card border-0 shadow-sm border-start border-4 border-primary">
    <div class="card-header bg-white fw-bold">Comparaison de scénarios</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr><th>Scénario</th><th class="text-end">Simulé</th><th class="text-end">Écart abs.</th><th class="text-end">Écart %</th></tr>
            </thead>
            <tbody>
            @foreach($comparaison['scenarios'] as $sc)
                <tr>
                    <td><strong>{{ $sc['code'] }}</strong> — {{ $sc['libelle'] }}</td>
                    <td class="text-end fw-semibold">{{ $fmt($sc['montant_simule']) }}</td>
                    <td class="text-end">{{ $fmt($sc['ecart_absolu']) }}</td>
                    <td class="text-end">{{ number_format($sc['ecart_pct'], 2) }} %</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@push('scripts')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.getElementById('sim-type')?.addEventListener('change', function () {
    const t = this.value;
    document.getElementById('field-taux').classList.toggle('d-none', t === 'enveloppe_plafond');
    document.getElementById('field-structure').classList.toggle('d-none', t !== 'structure_pct');
    document.getElementById('field-plafond').classList.toggle('d-none', t !== 'enveloppe_plafond');
});
</script>
@endpush
@endsection
