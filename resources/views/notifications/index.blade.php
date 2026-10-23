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

    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2 small"><strong>{{ $stats['non_lues'] }}</strong> non lues</div></div></div>

    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2 small"><strong>{{ $stats['aujourdhui'] }}</strong> aujourd'hui</div></div></div>

    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2 small"><strong>{{ $stats['critiques'] }}</strong> critiques / urgence</div></div></div>

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

        <div class="col-md-2">

            <label class="form-label small mb-0">Recherche</label>

            <input type="search" name="q" value="{{ $filtres['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Titre, message…">

        </div>

        <div class="col-md-1">

            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i></button>

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

                        @if(is_null($n->read_at))<span class="badge bg-primary">Nouveau</span>@endif

                    </div>

                    <div class="fw-semibold">{{ $data['titre'] ?? 'Notification' }}</div>

                    <div class="small text-muted">{{ $data['message'] ?? '' }}</div>

                    <div class="small text-secondary mt-1">{{ $n->created_at->diffForHumans() }}</div>

                </div>

                <div class="d-flex gap-1 flex-shrink-0 flex-wrap justify-content-end">

                    @if(!empty($data['url']) && $data['url'] !== url('/'))

                    <a href="{{ $data['url'] }}" class="btn btn-sm btn-outline-secondary">Voir</a>

                    @endif

                    @if(is_null($n->read_at))

                    <form action="{{ route('notifications.lue', $n->id) }}" method="POST">@csrf

                        <button class="btn btn-sm btn-primary">Lu</button>

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

