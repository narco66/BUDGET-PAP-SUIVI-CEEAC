@extends('layouts.app')
@section('titre', 'Tableau de bord nomenclature')

@push('styles')<style>
    .nom-code { font-family:monospace; color:#0a4a8f; }
    .stat-card { border-left:4px solid #0a4a8f; }
    .stat-num { font-size:1.7rem; font-weight:700; line-height:1; }
</style>@endpush

@section('contenu')
<a href="{{ route('nomenclature.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Nomenclature</a>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Tableau de bord de la nomenclature</h4>
        <span class="text-muted small">Volumétrie du référentiel et usage des lignes budgétaires</span>
    </div>
    <form method="GET">
        <select name="exercice" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()">
            @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
        </select>
    </form>
</div>

{{-- Volumétrie du référentiel --}}
<div class="row g-3 mb-1">
    @foreach (['titres' => 'Chapitres (Titres)', 'articles' => 'Articles', 'paragraphes' => 'Paragraphes', 'rubriques' => 'Lignes (Rubriques)'] as $cle => $libelle)
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm stat-card h-100"><div class="card-body">
            <div class="stat-num">{{ number_format($comptes[$cle], 0, ',', ' ') }}</div>
            <div class="text-muted small">{{ $libelle }}</div>
        </div></div>
    </div>
    @endforeach
</div>

<div class="row g-3 mt-1">
    {{-- Répartition des titres par sens --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-1"></i>Chapitres par sens budgétaire</div>
            <div class="card-body">
                @forelse ($comptes['titres_par_sens'] as $sens => $n)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><span class="badge bg-primary">{{ $sensListe[$sens] ?? $sens }}</span></span>
                        <span class="fw-semibold">{{ $n }}</span>
                    </div>
                @empty<p class="text-muted small mb-0">Aucun chapitre.</p>@endforelse
            </div>
        </div>
    </div>

    {{-- Usage des lignes budgétaires --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-stack me-1"></i>Usage des lignes budgétaires — Exercice {{ $exercice?->annee ?? '—' }}</div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-4"><div class="stat-num">{{ $usage['total'] }}</div><div class="text-muted small">Lignes</div></div>
                    <div class="col-4"><div class="stat-num text-success">{{ $usage['utilisees'] }}</div><div class="text-muted small">Utilisées</div></div>
                    <div class="col-4"><div class="stat-num text-warning">{{ $usage['inutilisees'] }}</div><div class="text-muted small">Inutilisées</div></div>
                </div>
                <div class="progress" style="height:1.3rem">
                    <div class="progress-bar bg-success" style="width:{{ $usage['taux_utilisation'] }}%">{{ $usage['taux_utilisation'] }}% engagées</div>
                </div>
                <div class="d-flex justify-content-between small text-muted mt-2">
                    <span>Crédit ouvert : {{ number_format($usage['montant_ouvert'], 0, ',', ' ') }} XAF</span>
                    <span>Engagé : {{ number_format($usage['montant_engage'], 0, ',', ' ') }} XAF</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    {{-- Lignes les plus sollicitées --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-fire me-1"></i>Lignes les plus sollicitées</div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th class="text-end">Imputations</th><th class="text-end">Engagé (XAF)</th></tr></thead>
                <tbody>
                @forelse ($sollicitees as $l)
                    <tr>
                        <td class="nom-code">{{ $l->code }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($l->intitule, 32) }}</td>
                        <td class="text-end"><span class="badge bg-danger">{{ $l->nb_imputations }}</span></td>
                        <td class="text-end small">{{ number_format($l->montant_engage, 0, ',', ' ') }}</td>
                    </tr>
                @empty<tr><td colspan="4" class="text-center text-muted py-4">Aucun engagement sur cet exercice.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    {{-- Lignes inutilisées --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-hourglass me-1"></i>Lignes inutilisées (sans engagement)</div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Structure</th><th class="text-end">Ouvert (XAF)</th></tr></thead>
                <tbody>
                @forelse ($inutilisees as $l)
                    <tr>
                        <td class="nom-code">{{ $l->code }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($l->intitule, 28) }}</td>
                        <td class="small">{{ $l->structure }}</td>
                        <td class="text-end small">{{ number_format($l->montant_ouvert, 0, ',', ' ') }}</td>
                    </tr>
                @empty<tr><td colspan="4" class="text-center text-muted py-4">Toutes les lignes sont engagées.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
