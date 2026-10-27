@extends('layouts.app')
@section('titre', $config['pluriel'])
@php
$fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
$statuts = [
    'brouillon' => ['Brouillon', 'secondary', 'pencil'],
    'soumis' => ['En validation', 'warning', 'hourglass-split'],
    'valide' => [$type === 'ordonnancement' ? 'Signé' : 'Validé', 'success', 'check-circle'],
    'rejete' => ['Rejeté', 'danger', 'x-circle'],
    'annule' => ['Annulé', 'dark', 'slash-circle'],
];
$suite = \App\Services\Depense\EtapeDepenseListeService::libelleSuite($type);
$export = ['liquidation' => 'depense.export.liquidations', 'ordonnancement' => 'depense.export.ordonnancements', 'paiement' => 'depense.export.paiements'][$type];
$filtresActifs = collect($filtres)->except(['tri', 'par_page'])->filter(fn ($v) => $v !== '')->isNotEmpty();
$lien = fn (array $params) => route('depense.index', array_merge([$type], request()->except('page'), $params));
$origine = fn ($it) => match ($type) {
    'liquidation' => $it->engagement,
    'ordonnancement' => $it->liquidation?->engagement,
    default => $it->ordonnancement?->liquidation?->engagement,
};
@endphp
@section('contenu')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi {{ $config['icone'] }} me-1"></i>{{ $config['pluriel'] }}</h4>
        <span class="text-muted small">
            {{ $config['parent_label'] }} {{ $type === 'liquidation' ? 'visé' : 'validé(e)' }} → {{ $config['label'] }}@if($config['enfant_label']) → {{ $config['enfant_label'] }}@endif
            · créé(e) depuis la fiche du dossier parent
        </span>
    </div>
    <div class="d-flex gap-2">
        @if($enAttenteMoi > 0)
            <a href="{{ route('taches.index') }}" class="btn btn-warning"><i class="bi bi-inbox me-1"></i>{{ $enAttenteMoi }} à traiter par moi</a>
        @endif
        <a href="{{ route('depense.tracabilite.index') }}" class="btn btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Traçabilité</a>
        @if(\Illuminate\Support\Facades\Route::has($export))
            <a href="{{ route($export) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Journal</a>
        @endif
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
        @continue(! $ind && $code === 'annule')
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
                <input type="search" name="q" value="{{ $filtres['q'] }}" class="form-control form-control-sm"
                       placeholder="N° {{ $config['prefixe'] }}, ENG, EB, bénéficiaire, objet{{ $type === 'paiement' ? ', réf. bancaire' : '' }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Statut</label>
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach($statuts as $code => [$libelle])<option value="{{ $code }}" @selected($filtres['statut'] === $code)>{{ $libelle }}</option>@endforeach
                </select>
            </div>
            @if($type === 'ordonnancement')
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Ordonnateur</label>
                <select name="ordonnateur" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach(\App\Services\Depense\EtapeDepenseListeService::ordonnateurs() as $code => $libelle)<option value="{{ $code }}" @selected($filtres['ordonnateur'] === $code)>{{ $libelle }}</option>@endforeach
                </select>
            </div>
            @endif
            <div class="col-6 col-md-1"><label class="form-label small mb-0">Du</label><input type="date" name="du" value="{{ $filtres['du'] }}" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0">Au</label><input type="date" name="au" value="{{ $filtres['au'] }}" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-1">
                <label class="form-label small mb-0">Tri</label>
                <select name="tri" class="form-select form-select-sm">
                    @foreach(\App\Services\Depense\EtapeDepenseListeService::TRIS as $code => $libelle)<option value="{{ $code }}" @selected($filtres['tri'] === $code)>{{ $libelle }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                @if($filtresActifs)<a href="{{ route('depense.index', $type) }}" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>@endif
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center small">
        <span>{{ $items->total() }} {{ strtolower($items->total() > 1 ? $config['pluriel'] : $config['label']) }}@if($filtresActifs) (filtré)@endif</span>
        <span class="d-flex align-items-center gap-1">Afficher
            @foreach([20, 50, 100] as $n)
                <a href="{{ $lien(['par_page' => $n]) }}" class="badge text-decoration-none {{ $filtres['par_page'] === $n ? 'bg-primary' : 'bg-light text-dark border' }}">{{ $n }}</a>
            @endforeach
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>N°</th>
                    <th>Origine / bénéficiaire</th>
                    <th class="text-end">Montant (XAF)</th>
                    @if($suite)<th class="text-end">{{ $suite }}</th>@endif
                    @if($type === 'ordonnancement')<th>Ordonnateur</th>@endif
                    @if($type === 'paiement')<th>Mode / référence</th>@endif
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Action attendue</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $it)
                @php
                    $eng = $origine($it);
                    $att = $attentes[$it->id] ?? null;
                    $st = $statuts[$it->statut] ?? [ucfirst(str_replace('_', ' ', $it->statut)), 'secondary', 'circle'];
                    $montant = (float) ($it->montant_xaf ?? $it->montant);
                    $fait = (float) ($it->suite_montant ?? 0);
                    $taux = $montant > 0 ? min(100, round($fait / $montant * 100)) : 0;
                @endphp
                <tr @class(['table-warning' => $att && $att['pour_moi']])>
                    <td class="text-nowrap">
                        <a href="{{ route('depense.show', [$type, $it->id]) }}" class="fw-semibold text-decoration-none"><code>{{ $it->numero }}</code></a>
                        <div class="small text-muted">{{ $it->createur?->name }}</div>
                    </td>
                    <td class="small" style="max-width: 280px">
                        @if($eng)
                            <div><code>{{ $eng->numero }}</code>@if($eng->expressionBesoin) · <code>{{ $eng->expressionBesoin->numero }}</code>@endif</div>
                            <div class="text-truncate" title="{{ $eng->objet }}">{{ $eng->beneficiaire ?: $eng->objet }}</div>
                        @else
                            <span class="text-muted">Origine non rattachée</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap fw-semibold">
                        {{ $fmt($montant) }}
                        @if($type === 'paiement' && $it->devise && $it->devise !== 'XAF')<div class="small text-muted">{{ $fmt($it->montant) }} {{ $it->devise }}</div>@endif
                    </td>
                    @if($suite)
                        <td class="text-end small" style="min-width: 130px">
                            @if($it->statut === 'valide')
                                <div>{{ $fmt($fait) }}</div>
                                <div class="progress" style="height: 4px"><div class="progress-bar bg-{{ $taux >= 100 ? 'success' : 'info' }}" style="width: {{ $taux }}%"></div></div>
                                <div class="text-muted">{{ $taux >= 100 ? 'Soldé' : 'Reste '.$fmt(max(0, $montant - $fait)) }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @endif
                    @if($type === 'ordonnancement')
                        <td class="small">{{ \App\Services\Depense\EtapeDepenseListeService::ordonnateurs()[$it->ordonnateur_role] ?? '—' }}</td>
                    @endif
                    @if($type === 'paiement')
                        <td class="small">{{ ucfirst((string) $it->mode_paiement) ?: '—' }}@if($it->reference_bancaire)<div class="text-muted">{{ $it->reference_bancaire }}</div>@endif</td>
                    @endif
                    <td class="small text-muted text-nowrap">{{ ($it->{$config['date_cle']} ?? $it->created_at)?->format('d/m/Y') }}</td>
                    <td><span class="badge bg-{{ $st[1] }}"><i class="bi bi-{{ $st[2] }} me-1"></i>{{ $st[0] }}</span></td>
                    <td class="small" style="max-width: 220px">
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
                        <a href="{{ route('depense.show', [$type, $it->id]) }}" class="btn btn-sm btn-outline-primary" title="Ouvrir"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('depense.tracabilite.show', [$type, $it->id]) }}" class="btn btn-sm btn-outline-secondary" title="Traçabilité"><i class="bi bi-diagram-3"></i></a>
                        <a href="{{ route('depense.pdf', [$type, $it->id]) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">
                    @if($filtresActifs)
                        Aucun résultat pour ces filtres. <a href="{{ route('depense.index', $type) }}">Réinitialiser</a>
                    @else
                        Aucun(e) {{ strtolower($config['label']) }}. Créez-la depuis un(e) {{ strtolower($config['parent_label']) }} {{ $type === 'liquidation' ? 'visé' : 'validé(e)' }}.
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $items->links() }}</div>
@endsection
