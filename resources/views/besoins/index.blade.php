@extends('layouts.app')
@section('titre', 'Expressions du besoin')
@php
use App\Services\Depense\BesoinListeService;
$fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
$statuts = BesoinListeService::STATUTS;
$prioriteC = ['haute' => 'danger', 'moyenne' => 'warning', 'basse' => 'secondary'];
$chaine = ['ENG', 'LIQ', 'ORD', 'PAY'];
$filtresActifs = collect($filtres)->except(['tri', 'par_page'])->filter(fn ($v) => $v !== '' && $v !== false)->isNotEmpty();
$lien = fn (array $params) => route('besoins.index', array_merge(request()->except('page'), $params));
$avances = $filtres['priorite'] !== '' || $filtres['categorie'] !== '' || $filtres['structure_id'] !== '' || $filtres['exercice_id'] !== '' || $filtres['tache_id'] !== '' || $filtres['du'] !== '' || $filtres['au'] !== '';
@endphp
@section('contenu')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-clipboard-plus me-1"></i>Expressions du besoin</h4>
        <span class="text-muted small">Point d'entrée de la chaîne de la dépense : EB → ENG → LIQ → ORD → PAY</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($enAttenteMoi > 0)
            <a href="{{ route('taches.index') }}" class="btn btn-warning btn-sm"><i class="bi bi-inbox me-1"></i>{{ $enAttenteMoi }} à traiter par moi</a>
        @endif
        <a href="{{ route('depense.tracabilite.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-diagram-3 me-1"></i>Traçabilité</a>
        @can('besoin.valider')
            <a href="{{ route('besoins.consolidation') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-collection me-1"></i>Consolidation</a>
        @endcan
        @can('paiement.valider')
            <a href="{{ route('depense.rapprochement.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bank me-1"></i>Rapprochement</a>
        @endcan
        @can('depenses.export')
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-file-earmark-excel me-1"></i>Exports</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Journaux (Excel)</h6></li>
                    @foreach ([
                        'depense.export.besoins' => 'Export des besoins',
                        'depense.export.engagements' => 'Journal des engagements',
                        'depense.export.liquidations' => 'Journal des liquidations',
                        'depense.export.ordonnancements' => 'Journal des ordonnancements',
                        'depense.export.paiements' => 'Journal des paiements',
                        'depense.export.consolide' => 'Journal consolidé',
                        'depense.export.ecritures' => 'Écritures comptables',
                        'depense.export.restes' => 'Restes à payer',
                    ] as $route => $libelle)
                        <li><a class="dropdown-item" href="{{ route($route) }}">{{ $libelle }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Archiver une version officielle traçable (versioning, hash, QR, GED)">
                    <i class="bi bi-shield-check me-1"></i>Archiver
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Journal officiel traçable</h6></li>
                    @foreach ([
                        'besoins' => 'Journal des besoins',
                        'engagements' => 'Journal des engagements',
                        'liquidations' => 'Journal des liquidations',
                        'ordonnancements' => 'Journal des ordonnancements',
                        'paiements' => 'Journal des paiements',
                        'consolide' => 'Journal consolidé',
                        'ecritures' => 'Écritures comptables',
                        'restes-a-payer' => 'Restes à payer',
                        'restes-a-engager' => 'Restes à engager',
                    ] as $cle => $libelle)
                        <li><a class="dropdown-item" href="{{ route('depense.export.archiver', $cle) }}"><i class="bi bi-file-earmark-check me-2"></i>{{ $libelle }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endcan
        @can('besoin.creer')<a href="{{ route('besoins.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau besoin</a>@endcan
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
        @continue(! $ind && in_array($code, ['a_corriger', 'converti'], true))
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
<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-0">Recherche</label>
                <input type="search" name="q" value="{{ $filtres['q'] }}" class="form-control form-control-sm" placeholder="N° EB, objet, demandeur, N° ENG, bénéficiaire">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Statut</label>
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach($statuts as $code => [$libelle])<option value="{{ $code }}" @selected($filtres['statut'] === $code)>{{ $libelle }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Tri</label>
                <select name="tri" class="form-select form-select-sm">
                    @foreach(BesoinListeService::TRIS as $code => $libelle)<option value="{{ $code }}" @selected($filtres['tri'] === $code)>{{ $libelle }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex align-items-center">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" name="mes" value="1" id="f-mes" @checked($filtres['mes'])>
                    <label class="form-check-label small" for="f-mes">Mes besoins</label>
                </div>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtres-avances" title="Plus de filtres"><i class="bi bi-sliders"></i></button>
                @if($filtresActifs)<a href="{{ route('besoins.index') }}" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>@endif
            </div>
        </div>
        <div class="collapse @if($avances) show @endif" id="filtres-avances">
            <div class="row g-2 align-items-end mt-1">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Priorité</label>
                    <select name="priorite" class="form-select form-select-sm">
                        <option value="">Toutes</option>
                        @foreach($priorites as $k => $v)<option value="{{ $k }}" @selected($filtres['priorite'] === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Catégorie</label>
                    <select name="categorie" class="form-select form-select-sm">
                        <option value="">Toutes</option>
                        <option value="pap" @selected($filtres['categorie'] === 'pap')>PAP</option>
                        <option value="hors_pap" @selected($filtres['categorie'] === 'hors_pap')>Hors PAP</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Structure</label>
                    <select name="structure_id" class="form-select form-select-sm">
                        <option value="">Toutes</option>
                        @foreach($structures as $s)<option value="{{ $s->id }}" @selected($filtres['structure_id'] == $s->id)>{{ $s->code }} · {{ \Illuminate\Support\Str::limit($s->libelle, 30) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small mb-0">Exercice</label>
                    <select name="exercice_id" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        @foreach($exercices as $e)<option value="{{ $e->id }}" @selected($filtres['exercice_id'] == $e->id)>{{ $e->annee }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Tâche PAP</label>
                    <select name="tache_id" class="form-select form-select-sm">
                        <option value="">Toutes</option>
                        @foreach($taches as $t)<option value="{{ $t->id }}" @selected($filtres['tache_id'] == $t->id)>{{ $t->code }} · {{ \Illuminate\Support\Str::limit($t->intitule, 35) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-1"><label class="form-label small mb-0">Du</label><input type="date" name="du" value="{{ $filtres['du'] }}" class="form-control form-control-sm"></div>
                <div class="col-6 col-md-1"><label class="form-label small mb-0">Au</label><input type="date" name="au" value="{{ $filtres['au'] }}" class="form-control form-control-sm"></div>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center small">
        <span>{{ $besoins->total() }} expression(s) du besoin @if($filtresActifs)(filtré)@endif</span>
        <span class="d-flex align-items-center gap-1">Afficher
            @foreach([20, 50, 100] as $n)
                <a href="{{ $lien(['par_page' => $n]) }}" class="badge text-decoration-none {{ $filtres['par_page'] === $n ? 'bg-primary' : 'bg-light text-dark border' }}">{{ $n }}</a>
            @endforeach
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr><th>N°</th><th>Objet</th><th>Structure / demandeur</th><th>Priorité</th><th class="text-end">Montant estimé</th><th>Statut</th><th>Avancement</th><th>Action attendue</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($besoins as $b)
                @php
                    $att = $attentes[$b->id] ?? null;
                    $av = $avancement[$b->id] ?? null;
                    $st = $statuts[$b->statut] ?? [ucfirst(str_replace('_', ' ', $b->statut)), 'secondary', 'circle'];
                    $rang = $av ? array_search($av['etape'], $chaine, true) : -1;
                @endphp
                <tr @class(['table-warning' => $att && $att['pour_moi']])>
                    <td class="text-nowrap">
                        <a href="{{ route('besoins.show', $b) }}" class="text-decoration-none"><code>{{ $b->numero }}</code></a>
                        <div class="small text-muted">{{ $b->created_at?->format('d/m/Y') }}</div>
                    </td>
                    <td class="small" style="max-width: 300px">
                        <div class="text-truncate" title="{{ $b->objet }}">{{ $b->objet }}</div>
                        <div class="text-muted text-truncate">
                            @if(($b->categorie ?? 'pap') === 'hors_pap')<span class="badge bg-light text-dark border">Hors PAP</span>@endif
                            @if($b->tache){{ $b->tache->code }}@elseif($b->activite){{ $b->activite->code }}@endif
                        </div>
                    </td>
                    <td class="small">
                        <div>{{ $b->structure?->code ?? '—' }}</div>
                        <div class="text-muted">{{ $b->demandeur?->name }}</div>
                    </td>
                    <td><span class="badge bg-{{ $prioriteC[$b->priorite] ?? 'secondary' }}">{{ $priorites[$b->priorite] ?? $b->priorite }}</span></td>
                    <td class="text-end text-nowrap fw-semibold">{{ $fmt($b->montant_estime) }}</td>
                    <td><span class="badge bg-{{ $st[1] }}"><i class="bi bi-{{ $st[2] }} me-1"></i>{{ $st[0] }}</span></td>
                    <td class="small" style="min-width: 150px">
                        @if($av)
                            <div class="d-flex gap-1 mb-1" title="Étape atteinte : {{ $av['etape'] }}">
                                @foreach($chaine as $i => $etape)
                                    <span class="badge {{ $i <= $rang ? 'bg-success' : 'bg-light text-muted border' }}" style="font-size: .65rem">{{ $etape }}</span>
                                @endforeach
                            </div>
                            <div class="text-muted"><code>{{ $b->engagement->numero }}</code>@if($av['paye'] > 0) · payé {{ $fmt($av['paye']) }}@endif</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
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
                        <a href="{{ route('besoins.show', $b) }}" class="btn btn-sm btn-outline-primary" title="Ouvrir"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('depense.tracabilite.show', ['besoin', $b->id]) }}" class="btn btn-sm btn-outline-secondary" title="Traçabilité"><i class="bi bi-diagram-3"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">
                    @if($filtresActifs)
                        Aucun résultat pour ces filtres. <a href="{{ route('besoins.index') }}">Réinitialiser</a>
                    @else
                        Aucune expression du besoin.
                        @can('besoin.creer') <a href="{{ route('besoins.create') }}">Créer la première</a>@endcan
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $besoins->links() }}</div>
@endsection
