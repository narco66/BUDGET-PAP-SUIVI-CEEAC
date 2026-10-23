@extends('layouts.app')
@section('titre', 'Enveloppes budgétaires')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2"></i>Enveloppes budgétaires</h5>
    @can('create', \App\Models\Budget::class)
    <a href="{{ route('budget.enveloppe.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouveau budget</a>
    @endcan
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr><th>Référence</th><th>Exercice</th><th>Libellé</th><th>Statut</th><th>Montant</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($budgets as $b)
                @php $c = ['brouillon'=>'secondary','en_validation'=>'warning','valide'=>'info','adopte'=>'success','rejete'=>'danger','en_revision'=>'primary','clos'=>'dark'][$b->statut] ?? 'secondary'; @endphp
                <tr>
                    <td><code>{{ $b->reference }}</code></td>
                    <td>{{ $b->exercice?->annee }}</td>
                    <td>{{ $b->libelle }}</td>
                    <td><span class="badge bg-{{ $c }}">{{ str_replace('_',' ',$b->statut) }}</span></td>
                    <td class="text-end">{{ number_format((float)$b->montant_total, 0, ',', ' ') }}</td>
                    <td class="text-end"><a href="{{ route('budget.enveloppe.show', $b) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Aucune enveloppe budgétaire.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-2">{{ $budgets->links() }}</div>
@endsection
