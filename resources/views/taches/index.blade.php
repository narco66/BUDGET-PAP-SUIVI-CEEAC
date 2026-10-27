@extends('layouts.app')
@section('titre', 'Mes tâches')
@php
    $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 0, ',', ' ');
    $user = auth()->user();
    $couleurPriorite = ['faible' => 'secondary', 'normale' => 'info', 'haute' => 'warning', 'urgente' => 'danger'];
    $couleurStatut = ['a_traiter' => 'primary', 'en_cours' => 'info', 'suspendue' => 'warning', 'terminee' => 'success', 'annulee' => 'secondary'];
    $onglets = ['mes' => ['Mes tâches à traiter', 'bi-inbox']];
    if ($peutUnite) { $onglets['unite'] = ['Tâches de mon unité', 'bi-people']; }
    $onglets += ['deleguees' => ['Tâches déléguées', 'bi-person-check'], 'traitees' => ['Tâches traitées', 'bi-check2-all'], 'dossiers' => ['Suivi de mes dossiers', 'bi-folder2-open']];
    $delai = fn ($h) => $h === null ? '—' : ($h < 1 ? max(1, (int) round($h * 60)).' min' : ($h < 48 ? number_format($h, 1, ',', ' ').' h' : number_format($h / 24, 1, ',', ' ').' j'));
    $q = fn (array $extra = []) => array_filter(array_merge($filtres, ['vue' => $vue], $extra), fn ($v) => $v !== null && $v !== '');
@endphp

@push('styles')<style>.cd-mt{font-variant-numeric:tabular-nums}.cd-step{border-left:4px solid #0a4a8f !important}.mt-ligne-retard{background:rgba(220,53,69,.05)}</style>@endpush

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-list-check me-2"></i>Mes tâches</h4>
        <span class="text-muted small">
            {{ $user->name }} · {{ $user->getRoleNames()->implode(', ') }}@if($user->structure) · {{ $user->structure->code }}@endif
            · actualisé à {{ \App\Support\Horodatage::local($actualiseLe, 'H:i') }}
        </span>
        @foreach ($delegations as $d)
            <div class="small text-primary"><i class="bi bi-person-badge me-1"></i>{{ $d['type'] }} en cours pour le compte de {{ $d['titulaire'] }}@if($d['fin']) jusqu'au {{ \Illuminate\Support\Carbon::parse($d['fin'])->format('d/m/Y') }}@endif</div>
        @endforeach
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('taches.index', $q()) }}" class="btn btn-sm btn-outline-secondary" title="Actualiser"><i class="bi bi-arrow-clockwise"></i></a>
        <a href="{{ route('taches.export', $q()) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-filetype-csv me-1"></i>Exporter</a>
        @if ($peutSuperviser)
            <a href="{{ route('taches.supervision') }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-bar-chart-line me-1"></i>Supervision</a>
        @endif
    </div>
</div>

{{-- Indicateurs (calculés en base sur le périmètre de l'utilisateur) --}}
<div class="row g-3 mb-3">
    @foreach ([
        ['À traiter', $stats['a_traiter'], 'text-primary', 'bi-inbox'],
        ['En cours', $stats['en_cours'], '', 'bi-hourglass-split'],
        ['Prioritaires / urgentes', $stats['urgentes'], 'text-warning', 'bi-exclamation-diamond'],
        ['En retard', $stats['en_retard'], 'text-danger', 'bi-alarm'],
        ['Terminées depuis le '.\Illuminate\Support\Carbon::parse($stats['depuis'])->format('d/m'), $stats['terminees_periode'], 'text-success', 'bi-check2-circle'],
    ] as [$lib, $val, $cls, $ico])
    <div class="col-6 col-lg"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold cd-mt {{ $cls }}">{{ $val }}</div>
        <div class="text-muted small"><i class="bi {{ $ico }} me-1"></i>{{ $lib }}</div>
    </div></div></div>
    @endforeach
</div>
<div class="row g-3 mb-3 small">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><span class="fw-bold cd-mt">{{ $stats['recues_periode'] }}</span> <span class="text-muted">reçues sur la période</span></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><span class="fw-bold cd-mt">{{ $stats['retournees_periode'] }}</span> <span class="text-muted">retournées pour correction</span> · <span class="fw-bold cd-mt">{{ $stats['deleguees_periode'] }}</span> <span class="text-muted">par délégation</span></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><span class="fw-bold cd-mt">{{ $delai($stats['delai_moyen_heures']) }}</span> <span class="text-muted">délai moyen de traitement</span></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><span class="fw-bold cd-mt">{{ $stats['taux_dans_delais'] !== null ? number_format($stats['taux_dans_delais'], 0, ',', ' ').' %' : '—' }}</span> <span class="text-muted">traitées dans les délais</span></div></div></div>
</div>

<ul class="nav nav-tabs mb-0">
    @foreach ($onglets as $cle => [$lib, $ico])
        <li class="nav-item"><a class="nav-link {{ $vue === $cle ? 'active fw-semibold' : '' }}" href="{{ route('taches.index', ['vue' => $cle]) }}"><i class="bi {{ $ico }} me-1"></i>{{ $lib }}@isset($compteurs[$cle]) <span class="badge rounded-pill {{ $vue === $cle ? 'bg-primary' : 'bg-secondary-subtle text-secondary-emphasis' }} ms-1">{{ $compteurs[$cle] }}</span>@endisset</a></li>
    @endforeach
</ul>

<div class="card border-0 shadow-sm border-top-0 rounded-top-0">
    {{-- Filtres combinables, appliqués côté serveur et conservés dans l'URL --}}
    <form method="GET" class="card-body border-bottom row g-2 align-items-end">
        <input type="hidden" name="vue" value="{{ $vue }}">
        <div class="col-md-3"><label class="form-label small mb-0">Recherche</label>
            <input type="search" name="q" value="{{ $filtres['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Référence, objet, étape…"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Module</label>
            <select name="module" class="form-select form-select-sm"><option value="">Tous</option>@foreach ($options['modules'] as $c => $l)<option value="{{ $c }}" @selected(($filtres['module'] ?? '') === $c)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Étape (rôle)</label>
            <select name="etape" class="form-select form-select-sm"><option value="">Toutes</option>@foreach ($options['etapes'] as $r)<option value="{{ $r }}" @selected(($filtres['etape'] ?? '') === $r)>{{ $r }}</option>@endforeach</select></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-0">Priorité</label>
            <select name="priorite" class="form-select form-select-sm"><option value="">Toutes</option>@foreach ($options['priorites'] as $c => $l)<option value="{{ $c }}" @selected(($filtres['priorite'] ?? '') === $c)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Échéance</label>
            <select name="echeance" class="form-select form-select-sm"><option value="">Toutes</option><option value="retard" @selected(($filtres['echeance'] ?? '') === 'retard')>En retard</option><option value="aujourdhui" @selected(($filtres['echeance'] ?? '') === 'aujourdhui')>Aujourd'hui</option><option value="semaine" @selected(($filtres['echeance'] ?? '') === 'semaine')>7 prochains jours</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Statut</label>
            <select name="statut" class="form-select form-select-sm"><option value="">Tous</option>@foreach ($options['statuts'] as $c => $l)<option value="{{ $c }}" @selected(($filtres['statut'] ?? '') === $c)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Unité initiatrice</label>
            <select name="structure_id" class="form-select form-select-sm"><option value="">Toutes</option>@foreach ($options['structures'] as $s)<option value="{{ $s->id }}" @selected((string) ($filtres['structure_id'] ?? '') === (string) $s->id)>{{ $s->code }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Affectation</label>
            <select name="affectation" class="form-select form-select-sm"><option value="">Toutes</option><option value="moi" @selected(($filtres['affectation'] ?? '') === 'moi')>Prises en charge par moi</option><option value="libre" @selected(($filtres['affectation'] ?? '') === 'libre')>Non prises en charge</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Reçues du</label><input type="date" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">au</label><input type="date" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-0">Montant min</label><input type="number" name="montant_min" value="{{ $filtres['montant_min'] ?? '' }}" class="form-control form-control-sm" min="0"></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-0">max</label><input type="number" name="montant_max" value="{{ $filtres['montant_max'] ?? '' }}" class="form-control form-control-sm" min="0"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Tri</label>
            <select name="tri" class="form-select form-select-sm">@foreach (['reception' => 'Date de réception', 'echeance' => 'Échéance', 'priorite' => 'Priorité', 'montant' => 'Montant', 'termine' => 'Date de traitement'] as $c => $l)<option value="{{ $c }}" @selected(($filtres['tri'] ?? ($vue === 'traitees' ? 'termine' : 'reception')) === $c)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-0">Sens</label>
            <select name="sens" class="form-select form-select-sm"><option value="desc" @selected(($filtres['sens'] ?? 'desc') === 'desc')>↓ récentes</option><option value="asc" @selected(($filtres['sens'] ?? 'desc') === 'asc')>↑</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Décision</label>
            <select name="decision" class="form-select form-select-sm"><option value="">Toutes</option>@foreach (\App\Models\WorkflowTache::DECISIONS as $c => $l)<option value="{{ $c }}" @selected(($filtres['decision'] ?? '') === $c)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrer</button>
            <a href="{{ route('taches.index', ['vue' => $vue]) }}" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>Référence</th><th>Module</th><th>Objet</th><th>Action attendue</th><th>Unité</th><th>Affectataire</th>
                <th>Reçue le</th><th>Échéance</th><th>Priorité</th><th class="text-end">Montant</th><th>Statut</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            @forelse ($taches as $t)
                @php $prio = $prioriteEffective($t); @endphp
                <tr class="{{ $t->enRetard() ? 'mt-ligne-retard' : '' }}">
                    <td class="small"><a href="{{ route('taches.show', $t) }}" class="fw-semibold">{{ $t->reference }}</a>
                        <div class="text-muted">{{ $t->objet?->numero ?? '#'.$t->objet_id }}</div></td>
                    <td><span class="badge bg-light text-dark border">{{ $t->module }}</span></td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($t->objet_libelle, 60) }}
                        @if ($nom = $initiateur($t))<div class="text-muted">Initiateur : {{ $nom }}</div>@endif</td>
                    <td class="small">{{ $t->libelle_etape ?? $t->role }}
                        <div class="text-muted">{{ $t->estPreparation() ? 'à réaliser dans le dossier' : 'étape '.$t->etape_ordre }} · {{ $t->role }}</div>
                        @if ($t->affectation_defaut && $t->estOuverte())<span class="badge bg-warning-subtle text-warning-emphasis border" title="Aucun titulaire organique paramétré : ouverte à tous les porteurs du rôle">affectation par défaut</span>@endif</td>
                    <td class="small">{{ $t->structure?->code ?? '—' }}</td>
                    <td class="small">{{ $t->assigne?->name ?? '—' }}</td>
                    <td class="small cd-mt">{{ \App\Support\Horodatage::local($t->created_at, 'd/m/Y H:i') }}</td>
                    <td class="small cd-mt {{ $t->enRetard() ? 'text-danger fw-semibold' : '' }}">{{ \App\Support\Horodatage::local($t->echeance_le, 'd/m/Y') }}@if($t->enRetard())<div><span class="badge bg-danger">Retard</span></div>@endif</td>
                    <td><span class="badge bg-{{ $couleurPriorite[$prio] ?? 'secondary' }}">{{ \App\Models\WorkflowTache::PRIORITES[$prio] ?? $prio }}</span></td>
                    <td class="text-end small cd-mt">{{ $fmt($montant($t)) }}</td>
                    <td>@if ($vue === 'dossiers')
                            {{-- Vu de l'initiateur : le dossier attend un autre acteur, ce n'est pas une tâche à traiter par lui. --}}
                            <span class="badge bg-secondary-subtle text-secondary-emphasis border text-wrap text-start">{{ $situationDossier($t) }}</span>
                        @else
                            <span class="badge bg-{{ $couleurStatut[$t->statut] ?? 'secondary' }}">{{ \App\Models\WorkflowTache::STATUTS[$t->statut] ?? $t->statut }}</span>
                        @endif
                        @if($t->decision)<div class="small text-muted">{{ \App\Models\WorkflowTache::DECISIONS[$t->decision] ?? $t->decision }}</div>@endif</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('taches.show', $t) }}" class="btn btn-sm btn-outline-primary" title="{{ in_array($vue, ['mes', 'deleguees'], true) ? 'Traiter' : 'Consulter' }}"><i class="bi {{ in_array($vue, ['mes', 'deleguees'], true) ? 'bi-box-arrow-in-right' : 'bi-eye' }}"></i></a>
                        @if ($vue === 'mes' && $t->statut === 'a_traiter' && ! $t->assigne_a)
                            <form method="POST" action="{{ route('taches.agir', [$t, 'prendre_en_charge']) }}" class="d-inline">@csrf
                                <input type="hidden" name="version" value="{{ $t->version }}">
                                <button class="btn btn-sm btn-outline-success" title="Prendre en charge"><i class="bi bi-hand-index"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center text-muted py-4">
                    @if ($vue === 'mes') Aucune tâche à traiter : votre file de travail est vide.
                    @elseif ($vue === 'traitees') Aucune tâche traitée.
                    @elseif ($vue === 'dossiers') Aucun de vos dossiers n'est en attente de validation.
                    @else Aucune tâche dans cette vue.@endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($taches->hasPages())
        <div class="card-body py-2">{{ $taches->appends($q())->links() }}</div>
    @endif
</div>
@endsection
