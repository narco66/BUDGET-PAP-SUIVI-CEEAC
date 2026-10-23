@extends('layouts.app')
@section('titre', 'Ligne '.$ligne->code)
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

@section('contenu')
<a href="{{ route('structure-budget.recherche', ['exercice' => $exercice?->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Recherche</a>

<div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-bold mb-0 font-monospace text-primary">{{ $ligne->code }}</h4>
        <p class="mb-0">{{ $ligne->intitule ?: '—' }}</p>
        <span class="text-muted small">Exercice {{ $ligne->exercice?->annee }} · {{ $ligne->structure?->libelle ?? 'Structure non rattachée' }}</span>
        @php $sv = $ligne->statut_validation ?? 'valide'; @endphp
        <span class="badge ms-1 bg-{{ $sv === 'valide' ? 'success' : ($sv === 'soumis' ? 'warning text-dark' : ($sv === 'rejete' ? 'danger' : 'secondary')) }}">{{ ucfirst($sv) }}</span>
        @unless($ligne->actif)<span class="badge bg-secondary ms-1">Désactivée</span>@endunless
        @if($sv === 'rejete' && $ligne->motif_rejet)<div class="small text-danger mt-1">Motif : {{ $ligne->motif_rejet }}</div>@endif
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('structureBudget.submitLigne', $ligne)
            <form method="POST" action="{{ route('structure-budget.ligne.soumettre', $ligne) }}">@csrf<button class="btn btn-outline-primary btn-sm"><i class="bi bi-send me-1"></i>Soumettre</button></form>
        @endcan
        @can('structureBudget.validateLigne', $ligne)
            <form method="POST" action="{{ route('structure-budget.ligne.valider', $ligne) }}">@csrf<button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Valider</button></form>
        @endcan
        @can('structureBudget.rejectLigne', $ligne)
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalRejet"><i class="bi bi-x-lg me-1"></i>Rejeter</button>
        @endcan
        @can('structureBudget.updateLigne', $ligne)
            <a href="{{ route('structure-budget.ligne.edit', $ligne) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Modifier</a>
        @endcan
        @can('structureBudget.archiveLigne', $ligne)
            @if($ligne->actif)
                <form method="POST" action="{{ route('structure-budget.ligne.archive', $ligne) }}" class="d-inline" onsubmit="return confirm('Désactiver cette ligne ?');">
                    @csrf
                    <button class="btn btn-outline-warning btn-sm">Désactiver</button>
                </form>
            @endif
        @endcan
        @can('structureBudget.deleteLigne', $ligne)
            <form method="POST" action="{{ route('structure-budget.ligne.destroy', $ligne) }}" class="d-inline" onsubmit="return confirm('Supprimer définitivement cette ligne ?');">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">Supprimer</button>
            </form>
        @endcan
    </div>
</div>

@can('structureBudget.rejectLigne', $ligne)
<div class="modal fade" id="modalRejet" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('structure-budget.ligne.rejeter', $ligne) }}" class="modal-content">@csrf
    <div class="modal-header"><h5 class="modal-title">Rejeter la ligne</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label">Motif de rejet <span class="text-danger">*</span></label><textarea name="motif_rejet" class="form-control" rows="3" required maxlength="1000"></textarea></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-danger">Confirmer le rejet</button></div>
</form></div></div>
@endcan

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
        <div class="fs-5 fw-bold">{{ $fmt($ligne->montant_ouvert) }}</div><div class="text-muted small">Ouvert</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
        <div class="fs-5 fw-bold text-primary">{{ $fmt($ligne->montant_engage) }}</div><div class="text-muted small">Engagé</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
        <div class="fs-5 fw-bold">{{ $fmt($ligne->disponible) }}</div><div class="text-muted small">Disponible</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center">
        <div class="fs-5 fw-bold">{{ $fmt($ligne->montant_paye) }}</div><div class="text-muted small">Payé</div>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Chaîne PAP / RBM</div>
            <div class="card-body">
                @forelse ($chaine_pap as $niveau => $item)
                    <div class="d-flex gap-2 mb-2 small">
                        <span class="badge bg-light text-dark text-capitalize" style="min-width:6rem">{{ str_replace('_', ' ', $niveau) }}</span>
                        <span class="font-monospace">{{ $item['code'] ?? '—' }}</span>
                        <span>{{ $item['intitule'] ?? '' }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0 small">Ligne hors PAP (aucune activité rattachée).</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Nomenclature budgétaire</div>
            <div class="card-body">
                @forelse ($chaine_nomenclature as $niveau => $item)
                    <div class="d-flex gap-2 mb-2 small">
                        <span class="badge bg-light text-dark text-capitalize" style="min-width:6rem">{{ $niveau }}</span>
                        <span class="font-monospace">{{ $item['code'] ?? '—' }}</span>
                        <span>{{ $item['intitule'] ?? '' }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0 small">Rubrique non renseignée.</p>
                @endforelse
                @if($ligne->sourceFinancement)
                    <hr class="my-2">
                    <div class="small"><strong>Source :</strong> {{ $ligne->sourceFinancement->libelle }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Imputations d'engagement (20 dernières)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Engagement</th><th class="text-end">Montant imputé</th></tr></thead>
                    <tbody>
                    @forelse ($imputations as $imp)
                        <tr>
                            <td class="small">{{ $imp->engagement?->reference ?? '#'.$imp->engagement_id }}</td>
                            <td class="text-end small">{{ $fmt($imp->montant) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-muted text-center py-3">Aucune imputation.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
