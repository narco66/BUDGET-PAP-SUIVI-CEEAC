@extends('layouts.app')
@section('titre', 'Utilisateur · '.$utilisateur->name)
@php $sc = ['actif'=>'success','suspendu'=>'warning','desactive'=>'secondary']; @endphp

@section('contenu')
<div class="row g-3">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm"><div class="card-body text-center">
            @if($utilisateur->avatar)<img src="{{ \Illuminate\Support\Facades\Storage::url($utilisateur->avatar) }}" class="rounded-circle mb-2" width="84" height="84" style="object-fit:cover;">
            @else<span class="rounded-circle bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center justify-content-center mb-2" style="width:84px;height:84px;font-size:2rem;">{{ strtoupper(substr($utilisateur->name,0,1)) }}</span>@endif
            <h5 class="fw-bold mb-0">{{ $utilisateur->name }}</h5>
            <div class="text-muted">{{ $utilisateur->email }}</div>
            <span class="badge bg-{{ $sc[$utilisateur->statut] ?? 'secondary' }} mt-1">{{ $utilisateur->statut }}</span>
            @if($utilisateur->doit_changer_mot_de_passe)<span class="badge bg-warning text-dark mt-1">mot de passe à changer</span>@endif

            <dl class="row text-start small mt-3 mb-0">
                <dt class="col-5">Matricule</dt><dd class="col-7">{{ $utilisateur->matricule ?? '—' }}</dd>
                <dt class="col-5">Structure</dt><dd class="col-7">{{ $utilisateur->structure?->libelle ?? '—' }}</dd>
                <dt class="col-5">Fonction</dt><dd class="col-7">{{ $utilisateur->fonction?->libelle ?? '—' }}</dd>
                <dt class="col-5">Poste</dt><dd class="col-7">{{ $utilisateur->poste?->libelle ?? '—' }}</dd>
                <dt class="col-5">Dernière connexion</dt><dd class="col-7">{{ $utilisateur->derniere_connexion_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </dl>
        </div></div>

        @can('utilisateur.modifier')
        <div class="card border-0 shadow-sm mt-3"><div class="card-body">
            <h6 class="fw-bold">Actions</h6>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('utilisateurs.edit', $utilisateur) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>
                <a href="{{ route('utilisateurs.scopes.edit', $utilisateur) }}" class="btn btn-sm btn-outline-info"><i class="bi bi-shield-check me-1"></i>Périmètres</a>
                <form action="{{ route('utilisateurs.reinitialiser', $utilisateur) }}" method="POST" onsubmit="return confirm('Réinitialiser le mot de passe ?')">@csrf<button class="btn btn-sm btn-outline-warning"><i class="bi bi-key me-1"></i>Réinit. MDP</button></form>
            </div>
            @if($utilisateur->id !== auth()->id())
            <form action="{{ route('utilisateurs.statut', $utilisateur) }}" method="POST" class="d-flex gap-2 mt-2">@csrf
                <select name="statut" class="form-select form-select-sm" style="width:150px;">
                    @foreach (['actif'=>'Actif','suspendu'=>'Suspendu','desactive'=>'Désactivé'] as $k=>$v)<option value="{{ $k }}" @selected($utilisateur->statut===$k)>{{ $v }}</option>@endforeach
                </select>
                <button class="btn btn-sm btn-outline-dark">Appliquer le statut</button>
            </form>
            @endif
        </div></div>
        @endcan
    </div>

    <div class="col-md-7">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h6 class="fw-bold">Rôles & profils</h6>
            <div>@forelse($utilisateur->roles as $r)<span class="badge bg-primary-subtle text-primary-emphasis">{{ $r->libelle ?? $r->name }}</span> @empty<span class="text-muted small">Aucun rôle</span>@endforelse</div>
            <div class="mt-2">@forelse($utilisateur->profils as $p)<span class="badge bg-info-subtle text-info-emphasis">{{ $p->libelle }}</span> @empty<span class="text-muted small">Aucun profil</span>@endforelse</div>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h6 class="fw-bold">Périmètre d'accès</h6>
            @forelse($utilisateur->accessScopes as $scope)
                <span class="badge bg-secondary">{{ str_replace('_',' ',$scope->type) }}@if($scope->scope_id) #{{ $scope->scope_id }}@endif</span>
            @empty<span class="text-muted small">Accès global (aucune restriction)</span>@endforelse
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-bold">Historique des affectations</div>
            <ul class="list-group list-group-flush">
                @forelse($utilisateur->historiquesAffectations->take(8) as $affectation)
                    <li class="list-group-item small d-flex justify-content-between">
                        <span>{{ $affectation->structure?->libelle ?? 'Sans structure' }}</span>
                        <span class="text-muted">{{ $affectation->date_debut?->format('d/m/Y') }} → {{ $affectation->date_fin?->format('d/m/Y') ?? '…' }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">Aucun historique enregistré.</li>
                @endforelse
            </ul>
        </div>

        @if($utilisateur->delegationsRecues->isNotEmpty() || $utilisateur->interimsAssures->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h6 class="fw-bold">Délégations & intérims actifs</h6>
            @foreach($utilisateur->delegationsRecues as $delegation)
                <div class="small mb-1"><span class="badge bg-warning text-dark">Délégation</span> {{ $delegation->structure?->libelle }} · depuis {{ $delegation->date_debut?->format('d/m/Y') }}</div>
            @endforeach
            @foreach($utilisateur->interimsAssures as $interim)
                <div class="small mb-1"><span class="badge bg-info text-dark">Intérim</span> {{ $interim->structure?->libelle }} · pour {{ $interim->titulaire?->name }}</div>
            @endforeach
        </div></div>
        @endif

        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold"><i class="bi bi-box-arrow-in-right me-1"></i>Dernières connexions</div>
            <ul class="list-group list-group-flush">
                @forelse($utilisateur->loginLogs as $log)
                    <li class="list-group-item small d-flex justify-content-between">
                        <span>{{ $log->created_at?->format('d/m/Y H:i') }} · {{ $log->ip }}</span>
                        @if($log->succes)<span class="badge bg-success">succès</span>@else<span class="badge bg-danger">échec</span>@endif
                    </li>
                @empty<li class="list-group-item text-muted small">Aucune connexion.</li>@endforelse
            </ul>
        </div>
    </div>
</div>
<div class="mt-3"><a href="{{ route('utilisateurs.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
@endsection
