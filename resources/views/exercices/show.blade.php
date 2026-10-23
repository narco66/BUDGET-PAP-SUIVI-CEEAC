@extends('layouts.app')
@section('titre', 'Exercice '.$exercice->code)

@section('contenu')
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-1">{{ $exercice->libelle }}</h5>
        <div class="text-muted small">
            Code <code>{{ $exercice->code }}</code> · Année {{ $exercice->annee }} · {{ $exercice->devise }}
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('update', $exercice)
            <a href="{{ route('exercices.edit', $exercice) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>
        @endcan
        @can('ouvrir', $exercice)
            @if($exercice->statut === 'brouillon')
            <form method="POST" action="{{ route('exercices.ouvrir', $exercice) }}">@csrf
                <button class="btn btn-sm btn-success" onclick="return confirm('Ouvrir cet exercice budgétaire ?')"><i class="bi bi-unlock me-1"></i>Ouvrir</button>
            </form>
            @endif
        @endcan
        @can('cloturer', $exercice)
            <form method="POST" action="{{ route('exercices.cloturer', $exercice) }}">@csrf
                <button class="btn btn-sm btn-warning" onclick="return confirm('Clôturer cet exercice ? Toute écriture budgétaire sera bloquée.')"><i class="bi bi-lock me-1"></i>Clôturer</button>
            </form>
        @endcan
        @can('activer', $exercice)
            @unless($exercice->est_actif)
            <form method="POST" action="{{ route('exercices.activer', $exercice) }}">@csrf
                <button class="btn btn-sm btn-primary"><i class="bi bi-star me-1"></i>Définir actif</button>
            </form>
            @endunless
        @endcan
        @can('delete', $exercice)
            <form method="POST" action="{{ route('exercices.destroy', $exercice) }}" onsubmit="return confirm('Supprimer cet exercice ?')">@csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        @endcan
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Identité</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">Statut</dt>
                    <dd class="col-7">
                        @if($exercice->statut === 'ouvert')<span class="badge bg-success">Ouvert</span>
                        @elseif($exercice->statut === 'clos')<span class="badge bg-secondary">Clôturé</span>
                        @else<span class="badge bg-warning text-dark">Brouillon</span>@endif
                    </dd>
                    <dt class="col-5">Exercice actif</dt>
                    <dd class="col-7">@if($exercice->est_actif)<span class="badge bg-primary">Oui</span>@else Non @endif</dd>
                    <dt class="col-5">Période</dt>
                    <dd class="col-7">{{ $exercice->date_debut?->format('d/m/Y') }} — {{ $exercice->date_fin?->format('d/m/Y') }}</dd>
                    @if($exercice->date_cloture)
                    <dt class="col-5">Clôturé le</dt>
                    <dd class="col-7">{{ $exercice->date_cloture->format('d/m/Y H:i') }}</dd>
                    <dt class="col-5">Par</dt>
                    <dd class="col-7">{{ $exercice->cloturePar?->name ?? '—' }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Rattachements</div>
            <div class="card-body small">
                <ul class="list-unstyled mb-0">
                    <li><i class="bi bi-cash-stack me-1"></i> {{ $exercice->lignes_budgetaires_count }} ligne(s) budgétaire(s)</li>
                    <li><i class="bi bi-diagram-3 me-1"></i> {{ $exercice->piliers_count }} pilier(s) RBM</li>
                    <li><i class="bi bi-journal-text me-1"></i> PAP : {{ $exercice->pap?->statut ?? 'non créé' }}</li>
                    <li><i class="bi bi-bank me-1"></i> {{ $exercice->contributions_count }} contribution(s)</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Accès rapide</div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('budget.index', ['exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-cash-stack me-1"></i>Budget & crédits</a>
                <a href="{{ route('rbm.hub', ['exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-diagram-3 me-1"></i>RBM / GAR</a>
                @if($exercice->pap)
                <a href="{{ route('paps.show', $exercice->pap) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-journal-text me-1"></i>Plan de performance</a>
                @endif
            </div>
        </div>
    </div>
</div>

@if($exercice->estClos())
<div class="alert alert-secondary mt-3 mb-0"><i class="bi bi-lock-fill me-1"></i> Exercice clôturé — aucune écriture budgétaire ou financière n'est autorisée.</div>
@endif
@endsection
