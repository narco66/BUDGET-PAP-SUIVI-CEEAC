@extends('layouts.app')
@section('titre', 'Import de données')

@php
    $couleurs = ['brouillon' => 'secondary', 'fichier_charge' => 'info', 'mapping' => 'warning', 'anomalies_detectees' => 'danger', 'simule' => 'primary',
        'en_attente_approbation' => 'warning', 'approuve' => 'info', 'en_cours' => 'info', 'termine' => 'success', 'echec' => 'danger', 'rejete' => 'danger', 'annule' => 'dark'];
    $enCours = collect(['fichier_charge', 'mapping', 'anomalies_detectees', 'simule', 'en_attente_approbation', 'approuve', 'en_cours'])->sum(fn ($s) => $stats[$s] ?? 0);
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-cloud-upload me-1"></i>Import de données</h1>
        <p class="text-muted small mb-0">Intégration contrôlée : analyse, correspondance, simulation, approbation puis exécution traçée. Aucune donnée n'est écrite sans votre confirmation.</p>
    </div>
    @can('create', \App\Models\ImportLot::class)
        <a href="{{ route('imports.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle importation</a>
    @endcan
</div>

<div class="row g-3 mb-3">
    @foreach ([
        ['Lots', $stats->sum(), 'text-primary', 'bi-collection'],
        ['Terminés', $stats['termine'] ?? 0, 'text-success', 'bi-check2-circle'],
        ['En cours / en attente', $enCours, 'text-warning', 'bi-hourglass-split'],
        ['Échecs ou rejets', ($stats['echec'] ?? 0) + ($stats['rejete'] ?? 0), 'text-danger', 'bi-x-octagon'],
        ['Lignes intégrées', $lignesImportees, '', 'bi-database-add'],
        ['Anomalies bloquantes / majeures', $anomalies, 'text-danger', 'bi-exclamation-triangle'],
    ] as [$libelle, $valeur, $classe, $icone])
        <div class="col-6 col-lg-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="fs-5 fw-bold {{ $classe }}">{{ number_format($valeur, 0, ',', ' ') }}</div>
            <div class="text-muted small"><i class="bi {{ $icone }} me-1"></i>{{ $libelle }}</div>
        </div></div></div>
    @endforeach
</div>

@if ($parDomaine->isNotEmpty())
<div class="card border-0 shadow-sm mb-3"><div class="card-body py-2 small">
    <span class="fw-semibold me-2">Par domaine :</span>
    @foreach ($parDomaine as $code => $n)
        <span class="badge bg-light text-dark border me-1">{{ $domaines[$code]?->libelle() ?? $code }} : {{ $n }}</span>
    @endforeach
</div></div>
@endif

<div class="card border-0 shadow-sm">
    <form method="GET" class="card-body border-bottom row g-2 align-items-end small">
        <div class="col-md-3"><label class="form-label mb-0">Domaine</label>
            <select name="domaine" class="form-select form-select-sm"><option value="">Tous</option>
                @foreach ($domaines as $code => $d)<option value="{{ $code }}" @selected(($filtres['domaine'] ?? '') === $code)>{{ $d->libelle() }}</option>@endforeach
            </select></div>
        <div class="col-md-2"><label class="form-label mb-0">Statut</label>
            <select name="statut" class="form-select form-select-sm"><option value="">Tous</option>
                @foreach (\App\Models\ImportLot::STATUTS as $code => $libelle)<option value="{{ $code }}" @selected(($filtres['statut'] ?? '') === $code)>{{ $libelle }}</option>@endforeach
            </select></div>
        @if ($utilisateurs->isNotEmpty())
        <div class="col-md-2"><label class="form-label mb-0">Utilisateur</label>
            <select name="utilisateur" class="form-select form-select-sm"><option value="">Tous</option>
                @foreach ($utilisateurs as $u)<option value="{{ $u->id }}" @selected((string) ($filtres['utilisateur'] ?? '') === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select></div>
        @endif
        <div class="col-6 col-md-2"><label class="form-label mb-0">Du</label><input type="date" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label mb-0">Au</label><input type="date" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-md-1 d-flex gap-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i></button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>Lot</th><th>Domaine</th><th>Fichier</th><th>Mode</th><th>Créé par</th><th>Le</th>
                <th class="text-end">Lignes</th><th class="text-end">À intégrer</th><th class="text-end">Anomalies</th><th>Statut</th>
            </tr></thead>
            <tbody>
            @forelse ($lots as $lot)
                <tr>
                    <td><a href="{{ route('imports.show', $lot) }}" class="fw-semibold">{{ $lot->reference }}</a></td>
                    <td class="small">{{ $domaines[$lot->domaine]?->libelle() ?? $lot->domaine }}@if ($lot->exercice)<div class="text-muted">Exercice {{ $lot->exercice->annee }}</div>@endif</td>
                    <td class="small text-truncate" style="max-width:14rem" title="{{ $lot->fichier_nom }}">{{ $lot->fichier_nom ?? '—' }}</td>
                    <td class="small">{{ \App\Models\ImportLot::MODES[$lot->mode] ?? $lot->mode }}</td>
                    <td class="small">{{ $lot->createur?->name ?? '—' }}</td>
                    <td class="small">{{ \App\Support\Horodatage::local($lot->created_at, 'd/m/Y H:i') }}</td>
                    <td class="text-end small">{{ $lot->total('lignes') }}</td>
                    <td class="text-end small">{{ $lot->statut === 'termine' ? $lot->total('executees').' intégrée(s)' : $lot->total('a_ecrire') }}</td>
                    <td class="text-end small">@if ($lot->total('bloquantes'))<span class="badge bg-danger">{{ $lot->total('bloquantes') }}</span>@endif @if ($lot->total('majeures'))<span class="badge bg-warning text-dark">{{ $lot->total('majeures') }}</span>@endif @if (! $lot->total('bloquantes') && ! $lot->total('majeures'))<span class="text-muted">—</span>@endif</td>
                    <td><span class="badge bg-{{ $couleurs[$lot->statut] ?? 'secondary' }}">{{ $lot->libelleStatut() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">Aucun lot d'import.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($lots->hasPages())<div class="card-body py-2">{{ $lots->links() }}</div>@endif
</div>
@endsection
