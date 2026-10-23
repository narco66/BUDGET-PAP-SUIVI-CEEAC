@extends('layouts.app')
@section('titre', 'Journal — Structure du budget')

@section('contenu')
<a href="{{ route('structure-budget.arbre', ['exercice' => $exercice?->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Structure du budget</a>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-clock-history me-1"></i>Journal — Structure du budget</h4>
        <span class="text-muted small">Traçabilité des créations, modifications, validations et imports/exports</span>
    </div>
    <form method="GET" class="d-flex gap-2">
        <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
        </select>
        <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Toutes actions</option>
            @foreach($actions as $a)<option value="{{ $a }}" @selected(request('action')===$a)>{{ $a }}</option>@endforeach
        </select>
    </form>
</div>

@if($enAttente->isNotEmpty())
<div class="card border-0 shadow-sm mb-3 border-warning">
    <div class="card-header bg-white fw-semibold text-warning"><i class="bi bi-hourglass-split me-1"></i>{{ $enAttente->count() }} ligne(s) en attente de validation</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Structure</th><th class="text-end">Montant</th><th>Soumis le</th><th></th></tr></thead>
        <tbody>@foreach($enAttente as $l)<tr>
            <td class="font-monospace small"><a href="{{ route('structure-budget.ligne.show', ['ligne' => $l, 'exercice' => $exercice?->id]) }}">{{ $l->code }}</a></td>
            <td class="small">{{ $l->structure?->sigle ?? '—' }}</td>
            <td class="text-end small">{{ number_format((float)$l->montant_ouvert, 0, ',', ' ') }}</td>
            <td class="small text-muted">{{ $l->soumis_le?->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="text-end">@can('structureBudget.validateLigne', $l)<form method="POST" action="{{ route('structure-budget.ligne.valider', $l) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Valider</button></form>@endcan</td>
        </tr>@endforeach</tbody>
    </table></div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Action</th><th>Entité</th><th>Utilisateur</th><th>Détails</th></tr></thead>
            <tbody>
            @forelse($entrees as $e)
                <tr>
                    <td class="small text-muted">{{ $e->created_at->format('d/m/Y H:i') }}</td>
                    <td><span class="badge bg-secondary">{{ $e->action }}</span></td>
                    <td class="small">@if($e->auditable_type)<code>{{ class_basename($e->auditable_type) }}</code> #{{ $e->auditable_id }}@else—@endif</td>
                    <td class="small">{{ $e->user_nom ?? '—' }}</td>
                    <td class="small text-muted">
                        @if(is_array($e->apres))
                            @if(isset($e->apres['code']))<code>{{ $e->apres['code'] }}</code>@endif
                            @if($e->apres['statut_validation'] ?? null) · {{ $e->apres['statut_validation'] }}@endif
                            @if($e->apres['motif_rejet'] ?? null) · {{ \Illuminate\Support\Str::limit($e->apres['motif_rejet'], 60) }}@endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Aucune entrée pour cet exercice.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($entrees, 'hasPages') && $entrees->hasPages())<div class="card-footer">{{ $entrees->links() }}</div>@endif
</div>
@endsection
