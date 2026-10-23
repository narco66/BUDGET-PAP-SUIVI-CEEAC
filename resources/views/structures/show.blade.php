@extends('layouts.app')
@section('titre', 'Structure · '.$structure->libelle)

@section('contenu')
<div class="row g-3">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">{{ $structure->libelle }} @if($structure->sigle)<span class="badge bg-light text-dark">{{ $structure->sigle }}</span>@endif</h5>
                <dl class="row mb-0 mt-3">
                    <dt class="col-sm-4">Code</dt><dd class="col-sm-8"><code>{{ $structure->code }}</code></dd>
                    <dt class="col-sm-4">Type</dt><dd class="col-sm-8">{{ str_replace('_',' ',$structure->type) }}</dd>
                    <dt class="col-sm-4">Rattachée à</dt><dd class="col-sm-8">{{ $structure->parent?->libelle ?? '—' }}</dd>
                    <dt class="col-sm-4">Responsable</dt><dd class="col-sm-8">{{ $structure->responsable?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">Niveau</dt><dd class="col-sm-8">{{ $structure->niveau }}</dd>
                    <dt class="col-sm-4">Statut</dt><dd class="col-sm-8">@if($structure->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif</dd>
                    <dt class="col-sm-4">Description</dt><dd class="col-sm-8">{{ $structure->description ?? '—' }}</dd>
                </dl>
                <hr>
                <h6 class="fw-bold">Structures rattachées ({{ $structure->enfants->count() }})</h6>
                <ul class="mb-0">
                    @forelse ($structure->enfants as $e)<li>{{ $e->code }} · {{ $e->libelle }}</li>@empty<li class="text-muted">Aucune</li>@endforelse
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-shield-lock me-1"></i>Piste d'audit</div>
            <div class="card-body" style="max-height:420px; overflow:auto;">
                @forelse ($structure->journaux()->latest('created_at')->limit(20)->get() as $j)
                    <div class="border-bottom pb-2 mb-2 small">
                        <span class="badge bg-{{ ['created'=>'success','updated'=>'warning','deleted'=>'danger'][$j->action] ?? 'secondary' }}">{{ $j->action }}</span>
                        <span class="text-muted">{{ $j->created_at?->format('d/m/Y H:i') }}</span><br>
                        <span>{{ $j->user_nom ?? 'Système' }}</span> · <span class="text-muted">{{ $j->ip }}</span>
                    </div>
                @empty
                    <div class="text-muted small">Aucune action journalisée.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
<div class="mt-3">
    <a href="{{ route('structures.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    @can('structure.modifier')<a href="{{ route('structures.edit', $structure) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>
@endsection
