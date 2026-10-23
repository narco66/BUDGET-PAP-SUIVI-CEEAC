@extends('layouts.app')
@section('titre', 'Préparation · '.$budget->reference)

@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">{{ $budget->libelle }}</h5>
        <small class="text-muted">{{ $budget->reference }} · Exercice {{ $budget->exercice?->annee }}</small>
    </div>
    <a href="{{ route('budget.enveloppe.show', $budget) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Enveloppe</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Propositions</div><div class="fs-5 fw-bold">{{ $synthese['total'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Demandé</div><div class="fs-5 fw-bold">{{ $fmt($synthese['montant_demande']) }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Proposé</div><div class="fs-5 fw-bold text-primary">{{ $fmt($synthese['montant_propose']) }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Arbitré</div><div class="fs-5 fw-bold text-success">{{ $fmt($synthese['montant_arbitre']) }}</div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap gap-2">
        @can('preparer', $budget)
        <form action="{{ route('budget.enveloppe.preparations.initialiser', $budget) }}" method="POST">@csrf
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Initialiser depuis les lignes</button>
        </form>
        @endcan
        @can('consolider', $budget)
        <form action="{{ route('budget.enveloppe.preparations.consolider', $budget) }}" method="POST" onsubmit="return confirm('Consolider les montants sur les lignes budgétaires ?')">@csrf
            <button class="btn btn-sm btn-success"><i class="bi bi-layers me-1"></i>Consolider</button>
        </form>
        @endcan
        @can('simuler', $budget)
        <a href="{{ route('budget.enveloppe.simulations', $budget) }}" class="btn btn-sm btn-outline-info"><i class="bi bi-sliders me-1"></i>Simulations</a>
        @endcan
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Propositions par ligne</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Réf.</th><th>Structure</th><th class="text-end">Demandé</th><th class="text-end">Proposé</th><th class="text-end">Arbitré</th><th>Statut</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($preparations as $p)
                        <tr>
                            <td><code>{{ $p->reference }}</code></td>
                            <td class="small">{{ $p->structure?->sigle ?? '—' }}</td>
                            <td class="text-end">{{ $fmt($p->montant_demande) }}</td>
                            <td class="text-end">{{ $fmt($p->montant_propose ?? 0) }}</td>
                            <td class="text-end fw-semibold">{{ $p->montant_arbitre ? $fmt($p->montant_arbitre) : '—' }}</td>
                            <td><span class="badge bg-light text-dark">{{ str_replace('_',' ',$p->statut) }}</span></td>
                            <td>
                                @can('preparer', $budget)
                                @if($p->estModifiable())
                                <form action="{{ route('budget.enveloppe.preparations.update', [$budget, $p]) }}" method="POST" class="d-flex gap-1">@csrf @method('PUT')
                                    <input type="number" name="montant_demande" value="{{ $p->montant_demande }}" class="form-control form-control-sm" style="width:110px;" step="1">
                                    <button class="btn btn-sm btn-outline-primary" title="Enregistrer"><i class="bi bi-check"></i></button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune proposition — cliquez sur « Initialiser ».</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Circuit de validation</div>
            <div class="card-body small">
                @can('preparer', $budget)
                <form action="{{ route('budget.enveloppe.preparations.soumettre', $budget) }}" method="POST" class="mb-2">@csrf
                    <label class="form-label">Soumettre (service)</label>
                    <select name="structure_id" class="form-select form-select-sm mb-1" required>
                        @foreach($parStructure as $row)
                        @if($row->structure_id)
                        <option value="{{ $row->structure_id }}">{{ $row->structure?->libelle ?? $row->structure_id }}</option>
                        @endif
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-primary w-100">Soumettre structure</button>
                </form>
                @endcan

                @can('validerPreparation', [$budget, 'direction'])
                <form action="{{ route('budget.enveloppe.preparations.valider', $budget) }}" method="POST" class="mb-2">@csrf
                    <input type="hidden" name="niveau" value="direction">
                    <button class="btn btn-sm btn-outline-primary w-100">Valider direction</button>
                </form>
                @endcan

                @can('validerPreparation', [$budget, 'departement'])
                <form action="{{ route('budget.enveloppe.preparations.valider', $budget) }}" method="POST" class="mb-2">@csrf
                    <input type="hidden" name="niveau" value="departement">
                    <button class="btn btn-sm btn-outline-primary w-100">Valider département</button>
                </form>
                @endcan

                @can('validerPreparation', [$budget, 'dppb'])
                <form action="{{ route('budget.enveloppe.preparations.valider', $budget) }}" method="POST" class="mb-2">@csrf
                    <input type="hidden" name="niveau" value="dppb">
                    <button class="btn btn-sm btn-warning w-100">Envoyer en arbitrage DPPB</button>
                </form>
                @endcan
            </div>
        </div>

        @can('arbitrer', $budget)
        <div class="card border-0 shadow-sm mb-3 border-start border-4 border-warning">
            <div class="card-header bg-white fw-bold">Arbitrage DPPB</div>
            <div class="card-body">
                <form action="{{ route('budget.enveloppe.preparations.arbitrer', $budget) }}" method="POST">@csrf
                    <select name="preparation_id" class="form-select form-select-sm mb-2" required>
                        @foreach($preparations->whereIn('statut', ['en_arbitrage','valide_departement']) as $p)
                        <option value="{{ $p->id }}">{{ $p->reference }} — {{ $fmt($p->montant_propose ?? $p->montant_demande) }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="montant_apres" class="form-control form-control-sm mb-2" placeholder="Montant arbitré" required step="1">
                    <textarea name="motif" class="form-control form-control-sm mb-2" rows="2" required placeholder="Motif de l'arbitrage"></textarea>
                    <button class="btn btn-sm btn-warning w-100">Enregistrer arbitrage</button>
                </form>
            </div>
        </div>
        @endcan

        @if($historiqueArbitrages->isNotEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Historique arbitrages</div>
            <ul class="list-group list-group-flush small">
                @foreach($historiqueArbitrages as $a)
                <li class="list-group-item">
                    <strong>{{ $a->preparation?->reference }}</strong> :
                    {{ $fmt($a->montant_avant) }} → {{ $fmt($a->montant_apres) }}
                    <div class="text-muted">{{ $a->auteur?->name }} · {{ $a->created_at->format('d/m/Y H:i') }}</div>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
