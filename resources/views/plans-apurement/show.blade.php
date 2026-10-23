@extends('layouts.app')
@section('titre', 'Plan d\'apurement '.$plan->reference)

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $badge = ['actif'=>'bg-primary','solde'=>'bg-success','annule'=>'bg-secondary'][$plan->statut] ?? 'bg-light text-dark';
@endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('appels-fonds.show', $plan->appel_fonds_id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Appel de fonds</a>
    @if ($plan->statut === 'actif' && !$plan->estVerrouille())
        @can('recette.recouvrement')
        <form method="POST" action="{{ route('plans-apurement.annuler', $plan) }}" onsubmit="return confirm('Annuler ce plan ?')">@csrf<button class="btn btn-sm btn-outline-secondary">Annuler le plan</button></form>
        @endcan
    @endif
</div>

@if (session('erreur'))<div class="alert alert-danger">{{ session('erreur') }}</div>@endif

<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h5 class="mb-0"><code>{{ $plan->reference }}</code></h5>
            <span class="badge {{ $badge }}">{{ \App\Models\PlanApurement::STATUTS[$plan->statut] ?? $plan->statut }}@if($plan->enDefaut()) · en défaut @endif</span>
        </div>
        <dl class="row mb-0">
            <dt class="col-sm-4 text-muted">Appel de fonds</dt><dd class="col-sm-8">{{ $plan->appel?->reference }} — {{ $plan->appel?->etatMembre?->pays ?? $plan->appel?->sourceFinancement?->libelle }}</dd>
            <dt class="col-sm-4 text-muted">Montant à apurer</dt><dd class="col-sm-8">{{ $fmt($plan->montant_total) }} XAF en {{ $plan->nombre_echeances }} échéance(s)</dd>
            <dt class="col-sm-4 text-muted">Début</dt><dd class="col-sm-8">{{ $plan->date_debut?->format('d/m/Y') }} · {{ $plan->etabliPar?->name }}</dd>
            <dt class="col-sm-4 text-muted">Observations</dt><dd class="col-sm-8">{{ $plan->observations ?? '—' }}</dd>
        </dl>
    </div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Réglé</div>
        <div class="h5 text-success">{{ $fmt($plan->montantRegle()) }} XAF</div>
        <div class="progress my-2" style="height:6px;"><div class="progress-bar bg-success" style="width: {{ $plan->montant_total > 0 ? min($plan->montantRegle() / $plan->montant_total * 100, 100) : 0 }}%"></div></div>
        <div class="text-muted small text-uppercase">Reste à apurer</div>
        <div class="h5 {{ $plan->solde() > 0 ? 'text-danger' : 'text-muted' }}">{{ $fmt($plan->solde()) }} XAF</div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold"><i class="bi bi-calendar-range me-1"></i>Échéancier</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>#</th><th>Échéance</th><th class="text-end">Prévu</th><th class="text-end">Réglé</th><th class="text-end">Solde</th><th class="text-center">Statut</th><th></th></tr></thead>
        <tbody>
        @foreach ($plan->echeances as $ech)
            <tr>
                <td>{{ $ech->rang }}</td>
                <td class="small">{{ $ech->date_echeance?->format('d/m/Y') }}@if($ech->enRetard())<span class="badge bg-danger ms-1">Retard</span>@endif</td>
                <td class="text-end">{{ $fmt($ech->montant_prevu) }}</td>
                <td class="text-end text-success">{{ $fmt($ech->montant_regle) }}</td>
                <td class="text-end">{{ $fmt($ech->solde()) }}</td>
                <td class="text-center"><span class="badge bg-light text-dark border">{{ \App\Models\EcheanceApurement::STATUTS[$ech->statut] ?? $ech->statut }}</span></td>
                <td class="text-end">
                    @if ($plan->statut === 'actif' && $ech->statut !== 'honoree')
                        @can('recette.recouvrement')
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#reg{{ $ech->id }}"><i class="bi bi-cash"></i></button>
                        @endcan
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>

@can('recette.recouvrement')
    @foreach ($plan->echeances as $ech)
        @if ($plan->statut === 'actif' && $ech->statut !== 'honoree')
        <div class="modal fade" id="reg{{ $ech->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('echeances-apurement.regler', $ech) }}">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Régler l'échéance #{{ $ech->rang }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="small text-muted">Solde de l'échéance : <strong>{{ $fmt($ech->solde()) }} XAF</strong></p>
                        <div class="mb-3">
                            <label class="form-label">Montant réglé (XAF) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="{{ $ech->solde() }}" name="montant" value="{{ $ech->solde() }}" class="form-control" required>
                        </div>
                        <div class="mb-1"><label class="form-label">Observations</label><textarea name="observations" rows="2" class="form-control"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-success">Enregistrer</button></div>
                </form>
            </div>
        </div>
        @endif
    @endforeach
@endcan
@endsection
