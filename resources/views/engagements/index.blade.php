@extends('layouts.app')
@section('titre', 'Engagements')
@php
use App\Services\Depense\EtapeDepenseListeService;
$fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
$statuts = [
    'brouillon' => ['Brouillon', 'secondary', 'pencil'],
    'soumis' => ['En validation', 'warning', 'hourglass-split'],
    'en_attente_arbitrage' => ['En arbitrage', 'info', 'sliders'],
    'vise' => ['Visé', 'success', 'check-circle'],
    'rejete' => ['Rejeté', 'danger', 'x-circle'],
    'annule' => ['Annulé', 'dark', 'slash-circle'],
];
$typesEngagement = ['simple' => 'Simple', 'bon_commande' => 'Bon de commande', 'marche' => 'Marché', 'mission' => 'Mission',
    'subvention' => 'Subvention', 'convention' => 'Convention', 'pluriannuel' => 'Pluriannuel', 'provisionnel' => 'Provisionnel'];
$filtresActifs = collect($filtres)->except(['tri', 'par_page'])->filter(fn ($v) => $v !== '')->isNotEmpty();
$lien = fn (array $params) => route('engagements.index', array_merge(request()->except('page'), $params));
@endphp
@section('contenu')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-receipt me-1"></i>Engagements</h4>
        <span class="text-muted small">Expression de besoin validée → Engagement → Liquidation · généré à la validation de l'EB</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($enAttenteMoi > 0)
            <a href="{{ route('taches.index') }}" class="btn btn-warning btn-sm"><i class="bi bi-inbox me-1"></i>{{ $enAttenteMoi }} à traiter par moi</a>
        @endif
        <a href="{{ route('depense.tracabilite.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-diagram-3 me-1"></i>Traçabilité</a>
        @can('depenses.export')
            <a href="{{ route('depense.export.engagements') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Journal</a>
        @endcan
        @can('engagement.creer')<a href="{{ route('engagements.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvel engagement</a>@endcan
    </div>
</div>

{{-- Indicateurs par statut (cliquables) --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md">
        <a href="{{ $lien(['statut' => null]) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-reset @if($filtres['statut'] === '') border-start border-4 border-primary @endif">
            <div class="card-body py-2">
                <div class="small text-muted"><i class="bi bi-collection me-1"></i>Tous</div>
                <div class="fs-5 fw-bold">{{ $total['n'] }}</div>
                <div class="small text-muted">{{ $fmt($total['montant']) }} XAF</div>
            </div>
        </a>
    </div>
    @foreach($statuts as $code => [$libelle, $couleur, $icone])
        @php $ind = $indicateurs[$code] ?? null; @endphp
        @continue(! $ind && in_array($code, ['soumis', 'en_attente_arbitrage', 'annule'], true))
        <div class="col-6 col-md">
            <a href="{{ $lien(['statut' => $code]) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-reset @if($filtres['statut'] === $code) border-start border-4 border-{{ $couleur }} @endif">
                <div class="card-body py-2">
                    <div class="small text-muted"><i class="bi bi-{{ $icone }} text-{{ $couleur }} me-1"></i>{{ $libelle }}</div>
                    <div class="fs-5 fw-bold">{{ (int) ($ind->n ?? 0) }}</div>
                    <div class="small text-muted">{{ $fmt($ind->total ?? 0) }} XAF</div>
                </div>
            </a>
        </div>
    @endforeach
</div>

{{-- Filtres --}}
<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-0">Recherche</label>
                <input type="search" name="q" value="{{ $filtres['q'] }}" class="form-control form-control-sm" placeholder="N° ENG, N° EB, bénéficiaire, objet">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Statut</label>
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach($statuts as $code => [$libelle])<option value="{{ $code }}" @selected($filtres['statut'] === $code)>{{ $libelle }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Type</label>
                <select name="type_engagement" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach($typesEngagement as $code => $libelle)<option value="{{ $code }}" @selected($filtres['type_engagement'] === $code)>{{ $libelle }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0">Du</label><input type="date" name="du" value="{{ $filtres['du'] }}" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0">Au</label><input type="date" name="au" value="{{ $filtres['au'] }}" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-1">
                <label class="form-label small mb-0">Tri</label>
                <select name="tri" class="form-select form-select-sm">
                    @foreach(EtapeDepenseListeService::TRIS as $code => $libelle)<option value="{{ $code }}" @selected($filtres['tri'] === $code)>{{ $libelle }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                @if($filtresActifs)<a href="{{ route('engagements.index') }}" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>@endif
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center small">
        <span>{{ $items->total() }} engagement(s) @if($filtresActifs)(filtré)@endif</span>
        <span class="d-flex align-items-center gap-1">Afficher
            @foreach([20, 50, 100] as $n)
                <a href="{{ $lien(['par_page' => $n]) }}" class="badge text-decoration-none {{ $filtres['par_page'] === $n ? 'bg-primary' : 'bg-light text-dark border' }}">{{ $n }}</a>
            @endforeach
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr><th>N°</th><th>Objet / bénéficiaire</th><th>Type</th><th class="text-end">Montant (XAF)</th><th class="text-end">Liquidé</th><th>Crédits</th><th>Statut</th><th>Action attendue</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($items as $e)
                @php
                    $att = $attentes[$e->id] ?? null;
                    $st = $statuts[$e->statut] ?? [ucfirst(str_replace('_', ' ', $e->statut)), 'secondary', 'circle'];
                    $montant = (float) $e->montant_total;
                    $liquide = (float) ($e->suite_montant ?? 0);
                    $taux = $montant > 0 ? min(100, round($liquide / $montant * 100)) : 0;
                @endphp
                <tr @class(['table-warning' => $att && $att['pour_moi']])>
                    <td class="text-nowrap">
                        <a href="{{ route('engagements.show', $e) }}" class="text-decoration-none"><code>{{ $e->numero }}</code></a>
                        <div class="small text-muted">@if($e->expressionBesoin)<code>{{ $e->expressionBesoin->numero }}</code>@else{{ $e->createur?->name }}@endif</div>
                    </td>
                    <td class="small" style="max-width: 300px">
                        <div class="text-truncate" title="{{ $e->objet }}">{{ $e->objet }}</div>
                        @if($e->beneficiaire)<div class="text-muted text-truncate">{{ $e->beneficiaire }}</div>@endif
                    </td>
                    <td class="small">{{ $typesEngagement[$e->type] ?? str_replace('_', ' ', (string) $e->type) }}</td>
                    <td class="text-end text-nowrap fw-semibold">{{ $fmt($montant) }}</td>
                    <td class="text-end small" style="min-width: 130px">
                        @if($e->statut === 'vise')
                            <div>{{ $fmt($liquide) }}</div>
                            <div class="progress" style="height: 4px"><div class="progress-bar bg-{{ $taux >= 100 ? 'success' : 'info' }}" style="width: {{ $taux }}%"></div></div>
                            <div class="text-muted">{{ $taux >= 100 ? 'Entièrement liquidé' : 'Reste '.$fmt(max(0, $montant - $liquide)) }}</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>@if($e->credits_reserves)<span class="badge bg-success-subtle text-success-emphasis">réservés</span>@else<span class="badge bg-light text-dark border">non réservés</span>@endif</td>
                    <td><span class="badge bg-{{ $st[1] }}"><i class="bi bi-{{ $st[2] }} me-1"></i>{{ $st[0] }}</span></td>
                    <td class="small" style="max-width: 200px">
                        @if($att)
                            <div class="text-truncate" title="{{ $att['libelle'] }}">{{ $att['libelle'] }}</div>
                            <div class="{{ $att['en_retard'] ? 'text-danger fw-semibold' : 'text-muted' }}">
                                @if($att['pour_moi'])<i class="bi bi-person-check me-1"></i>Vous @else{{ $att['acteur'] }}@endif
                                @if($att['en_retard']) · en retard @elseif($att['echeance']) · {{ $att['echeance']->format('d/m') }}@endif
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('engagements.show', $e) }}" class="btn btn-sm btn-outline-primary" title="Ouvrir"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('depense.tracabilite.show', ['engagement', $e->id]) }}" class="btn btn-sm btn-outline-secondary" title="Traçabilité"><i class="bi bi-diagram-3"></i></a>
                        <a href="{{ route('engagements.pdf', $e) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="Fiche PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">
                    @if($filtresActifs)
                        Aucun résultat pour ces filtres. <a href="{{ route('engagements.index') }}">Réinitialiser</a>
                    @else
                        Aucun engagement. Ils sont générés à la validation des expressions de besoin.
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $items->links() }}</div>
@endsection
