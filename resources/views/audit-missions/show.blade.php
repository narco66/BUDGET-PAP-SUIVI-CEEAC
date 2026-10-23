@extends('layouts.app')
@section('titre', 'Mission '.$mission->numero)
@php $sm = ['planifiee'=>'secondary','en_cours'=>'info','cloturee'=>'success','annulee'=>'dark']; @endphp

@section('contenu')
<a href="{{ route('audit-missions.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Missions d'audit</a>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><span class="font-monospace text-primary">{{ $mission->numero }}</span> — {{ $mission->libelle }}
        <span class="badge bg-{{ $sm[$mission->statut] ?? 'secondary' }} align-middle">{{ \App\Models\MissionAudit::STATUTS[$mission->statut] ?? $mission->statut }}</span></h4>
    @can('audit.modifier')@if($mission->estModifiable())<a href="{{ route('audit-missions.edit', $mission) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endif @endcan
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body">
        <table class="table table-sm mb-0">
            <tr><th class="text-muted" style="width:32%">Type</th><td>{{ \App\Models\MissionAudit::TYPES[$mission->type] ?? $mission->type }}</td></tr>
            <tr><th class="text-muted">Entité auditée</th><td>{{ $mission->structure?->libelle ?? '—' }}</td></tr>
            <tr><th class="text-muted">Chef de mission</th><td>{{ $mission->chefMission?->name ?? '—' }}</td></tr>
            <tr><th class="text-muted">Domaine</th><td>{{ $mission->domaine ?? '—' }}</td></tr>
            <tr><th class="text-muted">Période</th><td>{{ $mission->date_debut?->format('d/m/Y') ?? '—' }} → {{ $mission->date_fin?->format('d/m/Y') ?? '—' }}</td></tr>
            <tr><th class="text-muted">Objectif</th><td>{{ $mission->objectif ?? '—' }}</td></tr>
            <tr><th class="text-muted">Périmètre</th><td>{{ $mission->perimetre ?? '—' }}</td></tr>
            @if($mission->estCloturee())<tr><th class="text-muted">Conclusion</th><td>{{ $mission->conclusion ?? '—' }}</td></tr>
            <tr><th class="text-muted">Score de conformité</th><td>{{ $mission->score_conformite !== null ? $mission->score_conformite.'/100' : '—' }}</td></tr>@endif
        </table>
    </div></div></div>

    @if(! $mission->estCloturee())
    <div class="col-lg-5">
        @can('audit.valider')
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold"><i class="bi bi-check2-circle me-1"></i>Clôture de la mission</div>
            <div class="card-body">
                @if($mission->constatsOuverts() > 0)
                    <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>{{ $mission->constatsOuverts() }} recommandation(s) encore ouverte(s) : à solder avant clôture.</div>
                @endif
                <form method="POST" action="{{ route('audit-missions.cloturer', $mission) }}" onsubmit="return confirm('Clôturer définitivement cette mission ?')">
                    @csrf
                    <textarea name="conclusion" class="form-control mb-2" rows="3" placeholder="Conclusion de la mission *" required></textarea>
                    <input type="number" name="score_conformite" min="0" max="100" class="form-control mb-2" placeholder="Score de conformité /100">
                    <button class="btn btn-success w-100" @disabled($mission->constatsOuverts() > 0)><i class="bi bi-lock me-1"></i>Clôturer la mission</button>
                </form>
            </div>
        </div>
        @endcan
    </div>
    @endif
</div>

{{-- Constats & recommandations --}}
<h6 class="fw-bold mt-4 mb-2">Constats & recommandations ({{ $mission->constats->count() }})</h6>
@forelse ($mission->constats as $c)
    <div class="card border-0 shadow-sm mb-2"><div class="card-body py-2">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <span class="badge bg-{{ \App\Models\ConstatAudit::graviteClasse($c->gravite) }}">{{ \App\Models\ConstatAudit::GRAVITES[$c->gravite] ?? $c->gravite }}</span>
                <span class="font-monospace small text-muted">{{ $c->numero }}</span>
                <strong>{{ $c->libelle }}</strong>
            </div>
            <span class="badge bg-light text-dark">{{ \App\Models\ConstatAudit::STATUTS[$c->statut] ?? $c->statut }} · {{ $c->taux_avancement }}%</span>
        </div>
        @if($c->description)<div class="small text-muted mt-1">{{ $c->description }}</div>@endif
        @if($c->recommandation)<div class="small mt-1"><i class="bi bi-arrow-return-right text-primary"></i> <strong>Recommandation :</strong> {{ $c->recommandation }}</div>@endif
        <div class="small text-muted mt-1">Responsable : {{ $c->responsable?->name ?? '—' }} · Échéance : {{ $c->echeance?->format('d/m/Y') ?? '—' }}</div>
        <div class="progress mt-1" style="height:.5rem"><div class="progress-bar" style="width:{{ $c->taux_avancement }}%"></div></div>

        @can('audit.modifier')
        <form method="POST" action="{{ route('audit-missions.constats.update', [$mission, $c]) }}" class="row g-2 mt-1 align-items-end">
            @csrf @method('PUT')
            <div class="col-auto"><select name="statut" class="form-select form-select-sm">@foreach($statutsConstat as $k=>$v)<option value="{{ $k }}" @selected($c->statut===$k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-auto"><input type="number" name="taux_avancement" min="0" max="100" value="{{ $c->taux_avancement }}" class="form-control form-control-sm" style="width:90px" title="Taux %"></div>
            <div class="col-auto"><select name="responsable_user_id" class="form-select form-select-sm"><option value="">— Responsable —</option>@foreach($utilisateurs as $u)<option value="{{ $u->id }}" @selected($c->responsable_user_id==$u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="col-auto"><input type="date" name="echeance" value="{{ $c->echeance?->format('Y-m-d') }}" class="form-control form-control-sm"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Mettre à jour</button></div>
        </form>
        @endcan
    </div></div>
@empty<p class="text-muted small">Aucun constat enregistré.</p>@endforelse

{{-- Ajout d'un constat --}}
@can('audit.creer')
@if($mission->estModifiable())
<div class="card border-0 shadow-sm mt-2"><div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-1"></i>Ajouter un constat</div>
    <div class="card-body"><form method="POST" action="{{ route('audit-missions.constats.store', $mission) }}" class="row g-2">
        @csrf
        <div class="col-md-8"><input name="libelle" class="form-control form-control-sm" placeholder="Constat *" required></div>
        <div class="col-md-4"><select name="gravite" class="form-select form-select-sm" required>@foreach($gravites as $k=>$v)<option value="{{ $k }}" @selected($k==='moyenne')>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-6"><input name="cause" class="form-control form-control-sm" placeholder="Cause"></div>
        <div class="col-md-6"><input name="recommandation" class="form-control form-control-sm" placeholder="Recommandation"></div>
        <div class="col-md-4"><select name="responsable_user_id" class="form-select form-select-sm"><option value="">— Responsable —</option>@foreach($utilisateurs as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><input type="date" name="echeance" class="form-control form-control-sm" title="Échéance"></div>
        <div class="col-md-3"><select name="statut" class="form-select form-select-sm">@foreach($statutsConstat as $k=>$v)<option value="{{ $k }}" @selected($k==='ouvert')>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Ajouter</button></div>
    </form></div>
</div>
@endif
@endcan
@endsection
