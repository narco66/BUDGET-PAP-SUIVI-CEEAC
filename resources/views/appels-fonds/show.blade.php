@extends('layouts.app')
@section('titre', 'Appel de fonds '.$appel->reference)

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $badge = ['emis'=>'bg-secondary','notifie'=>'bg-info text-dark','partiellement_recouvre'=>'bg-warning text-dark','solde'=>'bg-success','annule'=>'bg-dark'][$appel->statut] ?? 'bg-light text-dark';
@endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('appels-fonds.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    <div class="d-flex gap-2">
        @if (!in_array($appel->statut, ['solde', 'annule']))
            @can('recouvrer', $appel)
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#recModal"><i class="bi bi-cash-coin me-1"></i>Enregistrer un recouvrement</button>
            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#relModal"><i class="bi bi-bell me-1"></i>Relancer</button>
            @endcan
        @endif
        @can('annuler', $appel)
            @if ($appel->statut !== 'annule' && (float) $appel->montant_regle == 0)
            <form method="POST" action="{{ route('appels-fonds.annuler', $appel) }}" onsubmit="return confirm('Annuler cet appel ?')">@csrf<button class="btn btn-sm btn-outline-secondary">Annuler l'appel</button></form>
            @endif
        @endcan
        @can('delete', $appel)
            <form method="POST" action="{{ route('appels-fonds.destroy', $appel) }}" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        @endcan
    </div>
</div>

@if (session('erreur'))<div class="alert alert-danger">{{ session('erreur') }}</div>@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h5 class="mb-0"><code>{{ $appel->reference }}</code></h5>
                <span class="badge {{ $badge }}">{{ \App\Models\AppelFonds::STATUTS[$appel->statut] ?? $appel->statut }}</span>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted">Objet</dt><dd class="col-sm-8">{{ $appel->objet }}</dd>
                <dt class="col-sm-4 text-muted">Exercice</dt><dd class="col-sm-8">{{ $appel->exercice?->annee }}</dd>
                <dt class="col-sm-4 text-muted">Débiteur</dt><dd class="col-sm-8">{{ $appel->etatMembre?->pays ?? $appel->sourceFinancement?->libelle ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Émis le</dt><dd class="col-sm-8">{{ $appel->date_emission?->format('d/m/Y') }} · {{ $appel->emisPar?->name }}</dd>
                <dt class="col-sm-4 text-muted">Échéance</dt><dd class="col-sm-8">{{ $appel->date_echeance?->format('d/m/Y') ?? '—' }} @if($appel->enRetard())<span class="badge bg-danger ms-1">En retard</span>@endif</dd>
                <dt class="col-sm-4 text-muted">Observations</dt><dd class="col-sm-8">{{ $appel->observations ?? '—' }}</dd>
            </dl>
        </div></div>
        <div class="mb-3">@include('partials._pieces', ['attachable' => $appel, 'type' => 'appel'])</div>

        @php $planActif = $appel->planApurementActif(); @endphp
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-range me-1"></i>Plan d'apurement</span>
                @if (!$planActif && $appel->solde > 0 && !in_array($appel->statut, ['solde','annule']))
                    @can('recouvrer', $appel)
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#apurModal"><i class="bi bi-plus-lg me-1"></i>Établir</button>
                    @endcan
                @endif
            </div>
            <div class="card-body">
                @if ($planActif)
                    <a href="{{ route('plans-apurement.show', $planActif) }}">{{ $planActif->reference }}</a>
                    — {{ $fmt($planActif->montant_total) }} XAF en {{ $planActif->nombre_echeances }} échéance(s)
                    · réglé {{ $fmt($planActif->montantRegle()) }} XAF
                    @if($planActif->enDefaut())<span class="badge bg-danger ms-1">En défaut</span>@endif
                @else
                    <span class="text-muted small">Aucun plan actif.</span>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-bell me-1"></i>Historique des relances ({{ $appel->relances->count() }})</div>
            <ul class="list-group list-group-flush">
                @forelse ($appel->relances as $relance)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span><span class="badge bg-warning text-dark">{{ $relance->libelleNiveau() }}</span>
                                {{ \App\Models\Relance::CANAUX[$relance->canal] ?? $relance->canal }}
                                @if($relance->automatique)<span class="badge bg-secondary">Auto</span>@endif
                            </span>
                            <span class="text-muted small">{{ $relance->date_relance?->format('d/m/Y') }}</span>
                        </div>
                        <div class="small text-muted">
                            Dû : {{ number_format((float) $relance->montant_du, 0, ',', ' ') }} XAF
                            @if($relance->destinataire) · {{ $relance->destinataire }} @endif
                            @if($relance->emisePar) · {{ $relance->emisePar->name }} @endif
                        </div>
                        @if($relance->observations)<div class="small">{{ $relance->observations }}</div>@endif
                    </li>
                @empty
                    <li class="list-group-item text-muted small">Aucune relance émise.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Montant appelé</div>
            <div class="h5">{{ $fmt($appel->montant_appele) }} XAF</div>
            <hr>
            <div class="text-muted small text-uppercase">Recouvré ({{ $appel->taux_paiement }}%)</div>
            <div class="h5 text-success">{{ $fmt($appel->montant_regle) }} XAF</div>
            <div class="progress my-2" style="height:6px;"><div class="progress-bar bg-success" style="width: {{ min($appel->taux_paiement, 100) }}%"></div></div>
            <hr>
            <div class="text-muted small text-uppercase">Arriéré (solde)</div>
            <div class="h5 {{ $appel->solde > 0 ? 'text-danger' : 'text-muted' }}">{{ $fmt($appel->solde) }} XAF</div>
            @if ($appel->taux_penalite > 0)
                <hr>
                <div class="text-muted small text-uppercase">Pénalité ({{ $appel->taux_penalite }}%)</div>
                <div class="h6 {{ $appel->penalite > 0 ? 'text-danger' : 'text-muted' }}">{{ $fmt($appel->penalite) }} XAF</div>
            @endif
        </div></div>
    </div>
</div>

@can('recouvrer', $appel)
@if ($appel->solde > 0 && !in_array($appel->statut, ['solde','annule']) && !$appel->planApurementActif())
<div class="modal fade" id="apurModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('plans-apurement.store', $appel) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Établir un plan d'apurement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Arriéré à apurer : <strong>{{ $fmt($appel->solde) }} XAF</strong></p>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Nombre d'échéances <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="60" name="nombre_echeances" value="3" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Date de début <span class="text-danger">*</span></label>
                        <input type="date" name="date_debut" value="{{ now()->toDateString() }}" class="form-control" required>
                    </div>
                    <div class="col-12 mt-2"><label class="form-label">Observations</label><textarea name="observations" rows="2" class="form-control"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Créer le plan (mensuel)</button></div>
        </form>
    </div>
</div>
@endif

<div class="modal fade" id="relModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('appels-fonds.relancer', $appel) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Émettre une relance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Niveau automatique : {{ \App\Models\Relance::NIVEAUX[min($appel->relances->count() + 1, 3)] ?? '' }} · Arriéré : <strong>{{ $fmt($appel->solde) }} XAF</strong></p>
                <div class="mb-3">
                    <label class="form-label">Canal <span class="text-danger">*</span></label>
                    <select name="canal" class="form-select" required>
                        @foreach (\App\Models\Relance::CANAUX as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Destinataire</label>
                    <input type="text" name="destinataire" value="{{ $appel->etatMembre?->pays ?? $appel->sourceFinancement?->libelle }}" class="form-control">
                </div>
                <div class="mb-1">
                    <label class="form-label">Observations</label>
                    <textarea name="observations" rows="2" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-warning">Émettre la relance</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="recModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('appels-fonds.recouvrer', $appel) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Enregistrer un recouvrement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Solde restant : <strong>{{ $fmt($appel->solde) }} XAF</strong></p>
                <div class="mb-3">
                    <label class="form-label">Montant recouvré (XAF) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" max="{{ $appel->solde }}" name="montant" class="form-control" required>
                </div>
                <div class="mb-1">
                    <label class="form-label">Observations</label>
                    <textarea name="observations" rows="2" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-success">Enregistrer</button></div>
        </form>
    </div>
</div>
@endcan
@endsection
