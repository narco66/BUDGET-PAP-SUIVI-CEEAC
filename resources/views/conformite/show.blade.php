@extends('layouts.app')
@section('titre', 'Non-conformité '.$nc->code)
@php $gc = \App\Models\NonConformite::graviteClasse($nc->gravite); @endphp

@section('contenu')
<a href="{{ route('conformite.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Non-conformités</a>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><span class="font-monospace text-primary">{{ $nc->code }}</span> — {{ $nc->libelle }}
        <span class="badge bg-{{ $gc }} align-middle">{{ \App\Models\NonConformite::GRAVITES[$nc->gravite] ?? $nc->gravite }}</span></h4>
    @can('conformite.modifier')<a href="{{ route('conformite.edit', $nc) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body">
        <table class="table table-sm mb-0">
            <tr><th class="text-muted" style="width:32%">Source</th><td>{{ \App\Models\NonConformite::SOURCES[$nc->source] ?? $nc->source }}</td></tr>
            <tr><th class="text-muted">Contrôle interne lié</th><td>{{ $nc->controleInterne?->code ?? '—' }} {{ $nc->controleInterne?->libelle }}</td></tr>
            <tr><th class="text-muted">Processus</th><td>{{ $nc->processus ?? '—' }}</td></tr>
            <tr><th class="text-muted">Structure</th><td>{{ $nc->structure?->libelle ?? '—' }}</td></tr>
            <tr><th class="text-muted">Date de détection</th><td>{{ $nc->date_detection?->format('d/m/Y') ?? '—' }}</td></tr>
            <tr><th class="text-muted">Description</th><td>{{ $nc->description ?: '—' }}</td></tr>
            <tr><th class="text-muted">Plan de remédiation</th><td>{{ $nc->plan_action ?: '—' }}</td></tr>
        </table>
    </div></div></div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold"><i class="bi bi-arrow-repeat me-1"></i>Suivi de la remédiation</div>
            <div class="card-body">
                <div class="d-flex justify-content-between small"><span>Statut</span><strong>{{ \App\Models\NonConformite::STATUTS[$nc->statut] ?? $nc->statut }}</strong></div>
                <div class="progress my-2" style="height:1rem"><div class="progress-bar" style="width:{{ $nc->taux_avancement }}%">{{ $nc->taux_avancement }}%</div></div>
                <div class="small text-muted">Responsable : {{ $nc->responsable?->name ?? '—' }} · Échéance : {{ $nc->echeance?->format('d/m/Y') ?? '—' }}</div>
                @can('conformite.modifier')
                <hr>
                <form method="POST" action="{{ route('conformite.suivi', $nc) }}" class="row g-2">
                    @csrf
                    <div class="col-12"><label class="form-label small mb-0">Statut</label><select name="statut" class="form-select form-select-sm">@foreach($statuts as $k=>$v)<option value="{{ $k }}" @selected($nc->statut===$k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small mb-0">Avancement %</label><input type="number" name="taux_avancement" min="0" max="100" value="{{ $nc->taux_avancement }}" class="form-control form-control-sm" required></div>
                    <div class="col-6"><label class="form-label small mb-0">Échéance</label><input type="date" name="echeance" value="{{ $nc->echeance?->format('Y-m-d') }}" class="form-control form-control-sm"></div>
                    <div class="col-12"><label class="form-label small mb-0">Responsable</label><select name="responsable_user_id" class="form-select form-select-sm"><option value="">— Aucun —</option>@foreach($utilisateurs as $u)<option value="{{ $u->id }}" @selected($nc->responsable_user_id==$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div class="col-12"><button class="btn btn-sm btn-primary w-100">Mettre à jour la remédiation</button></div>
                </form>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection
