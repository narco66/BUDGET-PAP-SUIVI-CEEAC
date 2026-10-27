@extends('layouts.app')

@section('titre', 'Notifications')



@section('contenu')

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">

    <h1 class="h5 mb-0"><i class="bi bi-bell me-2"></i>Centre de notifications</h1>

    <div class="d-flex gap-1 flex-wrap">

        @if($stats['non_lues'] > 0)

        <form action="{{ route('notifications.tout-lu') }}" method="POST">@csrf

            <button class="btn btn-sm btn-outline-primary">Tout marquer lu</button>

        </form>

        @endif

    </div>

</div>



<div class="row g-2 mb-3">
    @foreach ([
        [$stats['non_lues'], 'non lues', ['lu' => '0']],
        [$stats['actions_en_attente'], 'actions à traiter', ['action' => 'en_attente']],
        [$stats['critiques'], 'critiques non lues', ['priorite' => 'P1', 'lu' => '0']],
        [$stats['aujourdhui'], "reçues aujourd'hui", ['du' => now(\App\Support\Horodatage::fuseau())->toDateString()]],
        [$stats['taux_lecture'] !== null ? number_format($stats['taux_lecture'], 0, ',', ' ').' %' : '—', 'taux de lecture', null],
    ] as [$valeur, $libelle, $lien])
    <div class="col-6 col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 small">
        @if ($lien)<a href="{{ route('notifications.index', $lien) }}" class="text-decoration-none text-body">@endif
        <strong>{{ $valeur }}</strong> {{ $libelle }}
        @if ($lien)</a>@endif
    </div></div></div>
    @endforeach
</div>



<form method="GET" class="card border-0 shadow-sm mb-3">

    <div class="card-body row g-2 align-items-end">

        <div class="col-md-2">

            <label class="form-label small mb-0">Module</label>

            <select name="module" class="form-select form-select-sm">

                <option value="">Tous</option>

                @foreach($modules as $m)

                <option value="{{ $m }}" @selected(($filtres['module'] ?? '') === $m)>{{ strtoupper($m) }}</option>

                @endforeach

            </select>

        </div>

        <div class="col-md-3">

            <label class="form-label small mb-0">Type</label>

            <select name="type" class="form-select form-select-sm">

                <option value="">Tous</option>

                @foreach($types as $t)

                <option value="{{ $t->code }}" @selected(($filtres['type'] ?? '') === $t->code)>[{{ $t->module }}] {{ $t->libelle }}</option>

                @endforeach

            </select>

        </div>

        <div class="col-md-2">
            <label class="form-label small mb-0">Nature</label>
            <select name="nature" class="form-select form-select-sm">
                <option value="">Toutes</option>
                @foreach($natures as $c => $l)<option value="{{ $c }}" @selected(($filtres['nature'] ?? '') === $c)>{{ $l }}</option>@endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small mb-0">Priorité</label>
            <select name="priorite" class="form-select form-select-sm">
                <option value="">Toutes</option>
                @foreach($priorites as $c => $l)<option value="{{ $c }}" @selected(($filtres['priorite'] ?? '') === $c)>{{ $c }} · {{ $l }}</option>@endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small mb-0">Action</label>
            <select name="action" class="form-select form-select-sm">
                <option value="">Toutes</option>
                @foreach($actions as $c => $l)<option value="{{ $c }}" @selected(($filtres['action'] ?? '') === $c)>{{ $l }}</option>@endforeach
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Du</label>
            <input type="date" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control form-control-sm">
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Au</label>
            <input type="date" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control form-control-sm">
        </div>

        <div class="col-md-2">

            <label class="form-label small mb-0">Lu</label>

            <select name="lu" class="form-select form-select-sm">

                <option value="">Tous</option>

                <option value="0" @selected(($filtres['lu'] ?? '') === '0')>Non lues</option>

                <option value="1" @selected(($filtres['lu'] ?? '') === '1')>Lues</option>

            </select>

        </div>

        <div class="col-md-2">

            <label class="form-label small mb-0">Archives</label>

            <select name="archive" class="form-select form-select-sm">

                <option value="0" @selected(($filtres['archive'] ?? '0') === '0')>Actives</option>

                <option value="1" @selected(($filtres['archive'] ?? '') === '1')>Archivées</option>

            </select>

        </div>

        <div class="col-md-3">

            <label class="form-label small mb-0">Recherche</label>

            <input type="search" name="q" value="{{ $filtres['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Référence, titre, message…">

        </div>

        <div class="col-md-1 d-flex gap-1">

            <button class="btn btn-sm btn-primary flex-grow-1" title="Filtrer"><i class="bi bi-search"></i></button>
            <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>

        </div>

    </div>

</form>



<div class="card border-0 shadow-sm">

    <div class="list-group list-group-flush">

        @forelse($notifications as $n)

        @php

            $data = $n->data;

            $criticite = $n->criticite ?? ($data['criticite'] ?? 'information');

            $module = $n->module ?? ($data['module'] ?? 'systeme');

        @endphp

        <div class="list-group-item @if(is_null($n->read_at)) bg-light @endif">

            <div class="d-flex justify-content-between align-items-start gap-3">

                <div>

                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">

                        <span class="badge bg-{{ \App\Support\NotificationCriticite::badgeClass($criticite) }}">{{ $criticite }}</span>

                        <span class="badge bg-outline-secondary border text-secondary">{{ strtoupper($module) }}</span>

                        <span class="badge border text-secondary">{{ \App\Support\NotificationNature::LIBELLES[$n->nature ?? 'information'] ?? $n->nature }}</span>
                        @if($n->reference)<span class="badge border text-secondary">{{ $n->reference }}</span>@endif
                        @if(is_null($n->read_at))<span class="badge bg-primary">Non lue</span>@endif
                        @if($n->action_statut === 'en_attente')
                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> À traiter</span>
                        @elseif($n->action_statut)
                            <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check2-circle"></i> {{ \App\Support\NotificationNature::ETATS_ACTION[$n->action_statut] ?? $n->action_statut }}{{ !empty($data['meta']['traitee_par']) ? ' par '.$data['meta']['traitee_par'] : '' }}</span>
                        @endif

                    </div>

                    <div class="fw-semibold">{{ $data['titre'] ?? 'Notification' }}</div>

                    <div class="small text-muted">{{ $data['message'] ?? '' }}</div>

                    <div class="small text-secondary mt-1" title="{{ \App\Support\Horodatage::local($n->created_at) }}">{{ \App\Support\Horodatage::local($n->created_at, 'd/m/Y H:i') }} · {{ $n->created_at->diffForHumans() }}</div>

                </div>

                <div class="d-flex gap-1 flex-shrink-0 flex-wrap justify-content-end">

                    @if(!empty($data['url']) || $n->ressource_id)

                    <a href="{{ route('notifications.ouvrir', $n->id) }}" class="btn btn-sm {{ $n->action_statut === 'en_attente' ? 'btn-warning' : 'btn-outline-secondary' }}">{{ $n->action_statut === 'en_attente' ? 'Traiter' : 'Consulter' }}</a>

                    @endif

                    @if(is_null($n->read_at))

                    <form action="{{ route('notifications.lue', $n->id) }}" method="POST">@csrf

                        <button class="btn btn-sm btn-primary">Lu</button>

                    </form>

                    @else

                    <form action="{{ route('notifications.non-lue', $n->id) }}" method="POST">@csrf

                        <button class="btn btn-sm btn-outline-primary" title="Marquer comme non lue" aria-label="Marquer comme non lue"><i class="bi bi-envelope"></i></button>

                    </form>

                    @endif

                    @can('notifications.manage')

                    <form action="{{ route('notifications.archiver', $n->id) }}" method="POST">@csrf

                        <button class="btn btn-sm btn-outline-dark" title="Archiver"><i class="bi bi-archive"></i></button>

                    </form>

                    @endcan

                </div>

            </div>

        </div>

        @empty

        <div class="list-group-item text-center text-muted py-4">Aucune notification pour ces critères.</div>

        @endforelse

    </div>

</div>



<div class="mt-3">{{ $notifications->links() }}</div>

@endsection

