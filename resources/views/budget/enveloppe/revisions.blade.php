@extends('layouts.app')
@section('titre', 'Révisions · '.$budget->reference)

@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">Révisions et programmation pluriannuelle</h5>
        <small class="text-muted">{{ $budget->reference }} · Exercice {{ $budget->exercice?->annee }} · {{ str_replace('_',' ',$budget->statut) }}</small>
    </div>
    <a href="{{ route('budget.enveloppe.show', $budget) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Enveloppe</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Budget exercice</div><div class="fs-5 fw-bold">{{ $fmt($rapprochement['montant_budget']) }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Enveloppe pluriannuelle</div><div class="fs-5 fw-bold text-primary">{{ $fmt($rapprochement['enveloppe_pluriannuelle']) }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Écart</div><div class="fs-5 fw-bold {{ $rapprochement['ecart_absolu'] < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($rapprochement['ecart_absolu']) }} @if($rapprochement['ecart_pct'] !== null)({{ number_format($rapprochement['ecart_pct'], 1) }} %)@endif</div></div></div></div>
</div>

@can('rectifier', $budget)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-bold">Lien programmation pluriannuelle</div>
    <div class="card-body">
        @if($budget->programmationPluriannuelle)
        <p class="small mb-2">Rattaché : <code>{{ $budget->programmationPluriannuelle->reference }}</code> ({{ $budget->programmationPluriannuelle->annee_debut }}-{{ $budget->programmationPluriannuelle->annee_fin }})</p>
        @endif
        <form action="{{ route('budget.enveloppe.revisions.lier-pluri', $budget) }}" method="POST" class="row g-2 align-items-end">@csrf
            <div class="col-md-8">
                <label class="form-label small">Programmation pluriannuelle</label>
                <select name="programmation_pluriannuelle_id" class="form-select form-select-sm" required>
                    @foreach($pluriannuelles as $p)
                    <option value="{{ $p->id }}" @selected($budget->programmation_pluriannuelle_id == $p->id)>{{ $p->reference }} — {{ $p->planStrategique?->reference ?? 'PSC' }} ({{ $p->annee_debut }}-{{ $p->annee_fin }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4"><button class="btn btn-sm btn-outline-primary w-100">Rattacher</button></div>
        </form>
    </div>
</div>

@if($glissement !== [])
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-bold">Glissement pluriannuel</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Année</th><th class="text-end">Enveloppe estimée</th><th>Statut prog.</th></tr></thead>
            <tbody>
            @foreach($glissement as $g)
                <tr class="{{ $g['est_exercice_courant'] ? 'table-primary' : '' }}">
                    <td>{{ $g['annee'] }} @if($g['est_exercice_courant'])<span class="badge bg-primary ms-1">courant</span>@endif</td>
                    <td class="text-end">{{ $fmt($g['enveloppe_estimee']) }}</td>
                    <td class="small">{{ $g['statut'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Rectificatif</div>
            <div class="card-body">
                <form action="{{ route('budget.enveloppe.revisions.rectificatif', $budget) }}" method="POST">@csrf
                    <div class="mb-2">
                        <label class="form-label small">Ligne</label>
                        <select name="ligne_budgetaire_id" class="form-select form-select-sm" required>
                            @foreach($lignes as $l)
                            <option value="{{ $l->id }}">{{ $l->code }} — {{ $fmt($l->montant_alloue) }} FCFA (eng. {{ $fmt($l->montant_engage) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Nouveau montant (FCFA)</label>
                        <input type="number" name="montant_apres" class="form-control form-control-sm" min="0" step="1" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Motif</label>
                        <textarea name="motif" class="form-control form-control-sm" rows="2" required></textarea>
                    </div>
                    <button class="btn btn-sm btn-warning"><i class="bi bi-pencil-square me-1"></i>Appliquer rectificatif</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Virement de crédits</div>
            <div class="card-body">
                <form action="{{ route('budget.enveloppe.revisions.virement', $budget) }}" method="POST">@csrf
                    <div class="mb-2">
                        <label class="form-label small">Ligne source</label>
                        <select name="ligne_source_id" class="form-select form-select-sm" required>
                            @foreach($lignes as $l)
                            <option value="{{ $l->id }}">{{ $l->code }} — dispo. {{ $fmt($l->disponible) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Ligne destination</label>
                        <select name="ligne_destination_id" class="form-select form-select-sm" required>
                            @foreach($lignes as $l)
                            <option value="{{ $l->id }}">{{ $l->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Montant (FCFA)</label>
                        <input type="number" name="montant" class="form-control form-control-sm" min="1" step="1" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Motif</label>
                        <textarea name="motif" class="form-control form-control-sm" rows="2" required></textarea>
                    </div>
                    <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-left-right me-1"></i>Appliquer virement</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endcan

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold">Historique des révisions</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr><th>Réf.</th><th>Type</th><th class="text-end">Montant</th><th>Statut</th><th>Motif</th><th>Date</th></tr>
            </thead>
            <tbody>
            @forelse($historique as $r)
                <tr>
                    <td><code>{{ $r->reference }}</code></td>
                    <td>{{ \App\Models\BudgetRevision::TYPES[$r->type] ?? $r->type }}</td>
                    <td class="text-end">{{ $fmt($r->montant_total) }}</td>
                    <td><span class="badge bg-light text-dark">{{ $r->statut }}</span></td>
                    <td class="small">{{ Str::limit($r->motif, 60) }}</td>
                    <td class="small text-muted">{{ $r->valide_le?->format('d/m/Y H:i') ?? $r->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Aucune révision enregistrée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
