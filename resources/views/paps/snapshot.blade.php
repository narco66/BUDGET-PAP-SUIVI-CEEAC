@extends('layouts.app')
@section('titre', 'Snapshot PAP v'.$historique->version)

@php
    $snap = $historique->snapshot ?? [];
    $meta = $snap['meta'] ?? [];
    $synthese = $snap['synthese'] ?? [];
    $audit = $snap['audit'] ?? [];
    $piliers = $snap['piliers'] ?? [];
    $risques = $snap['risques'] ?? [];
    $mesures = $snap['mesures_correctives'] ?? [];
@endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-bold mb-1">Snapshot PAP — v{{ $historique->version }}</h4>
        <p class="text-muted mb-0 small">
            Action : <strong>{{ $historique->action }}</strong>
            · {{ $historique->created_at->format('d/m/Y H:i') }}
            @if($historique->auteur) · {{ $historique->auteur->name }}@endif
            @if($historique->snapshot_complet)<span class="badge bg-success ms-1">Complet</span>@endif
        </p>
    </div>
    <a href="{{ route('paps.show', $pap) }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour au PAP</a>
</div>

@if($historique->snapshot_complet && !empty($meta))
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i>Métadonnées</h6>
                <dl class="row small mb-0">
                    <dt class="col-5">Référence</dt><dd class="col-7">{{ $meta['reference'] ?? '—' }}</dd>
                    <dt class="col-5">Exercice</dt><dd class="col-7">{{ $meta['exercice'] ?? '—' }}</dd>
                    <dt class="col-5">Statut</dt><dd class="col-7">{{ $meta['statut'] ?? '—' }}</dd>
                    <dt class="col-5">Capturé le</dt><dd class="col-7">{{ isset($meta['capture_le']) ? \Carbon\Carbon::parse($meta['capture_le'])->format('d/m/Y H:i') : '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi bi-speedometer2 me-1"></i>Synthèse performance</h6>
                <div class="row text-center g-2">
                    <div class="col-4"><div class="small text-muted">Activités</div><div class="fs-5 fw-bold">{{ $synthese['activites'] ?? '—' }}</div></div>
                    <div class="col-4"><div class="small text-muted">Physique</div><div class="fs-5 fw-bold text-primary">{{ $synthese['taux_physique'] ?? '—' }}%</div></div>
                    <div class="col-4"><div class="small text-muted">Financier</div><div class="fs-5 fw-bold text-success">{{ $synthese['taux_financier'] ?? '—' }}%</div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi bi-shield-check me-1"></i>Audit & GAR</h6>
                <p class="mb-1 small">Anomalies audit : <strong>{{ is_array($audit) ? ($audit['anomalies_total'] ?? 0) : $audit }}</strong></p>
                <p class="mb-0 small">Risques : <strong>{{ count($risques) }}</strong> · Mesures : <strong>{{ count($mesures) }}</strong></p>
            </div>
        </div>
    </div>
</div>

@if(!empty($snap['enveloppe']))
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-bold">Enveloppe PAP</div>
    <div class="card-body small">
        <strong>{{ $snap['enveloppe']['libelle'] ?? '' }}</strong>
        @if(!empty($snap['enveloppe']['structure'])) · {{ $snap['enveloppe']['structure'] }}@endif
        @if(!empty($snap['enveloppe']['responsable'])) · {{ $snap['enveloppe']['responsable'] }}@endif
        @if(!empty($snap['enveloppe']['observations']))<div class="mt-2 text-muted">{{ $snap['enveloppe']['observations'] }}</div>@endif
    </div>
</div>
@endif

@if(count($risques) > 0)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-bold">Risques ({{ count($risques) }})</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Code</th><th>Libellé</th><th>Prob.</th><th>Impact</th><th>Statut</th></tr></thead>
            <tbody>
                @foreach($risques as $r)
                <tr>
                    <td><code>{{ $r['code'] ?? '' }}</code></td>
                    <td>{{ $r['libelle'] ?? '' }}</td>
                    <td>{{ $r['probabilite'] ?? '' }}</td>
                    <td>{{ $r['impact'] ?? '' }}</td>
                    <td>{{ $r['statut'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(count($piliers) > 0)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-3 me-1"></i>Arbre RBM ({{ count($piliers) }} pilier(s))</div>
    <div class="card-body small">
        @foreach($piliers as $pilier)
        <div class="mb-3">
            <div class="fw-semibold"><code>{{ $pilier['code'] ?? '' }}</code> — {{ $pilier['intitule'] ?? '' }}</div>
            @foreach($pilier['axes'] ?? [] as $axe)
            <div class="ms-3 mt-1 text-muted"><code>{{ $axe['code'] ?? '' }}</code> {{ $axe['intitule'] ?? '' }}</div>
            @foreach($axe['produits'] ?? [] as $prod)
            <div class="ms-4"><code>{{ $prod['code'] ?? '' }}</code> {{ $prod['intitule'] ?? '' }}
                @foreach($prod['activites'] ?? [] as $act)
                <div class="ms-3 border-start ps-2 my-1">
                    <code>{{ $act['code'] ?? '' }}</code> {{ Str::limit($act['intitule'] ?? '', 50) }}
                    <span class="text-muted">· Phys. {{ $act['taux_physique'] ?? 0 }}% · Fin. {{ $act['taux_financier'] ?? 0 }}%</span>
                    @if(!empty($act['cause_ecart']))<div class="fst-italic">Écart : {{ Str::limit($act['cause_ecart'], 80) }}</div>@endif
                </div>
                @endforeach
            </div>
            @endforeach
            @endforeach
        </div>
        @endforeach
    </div>
</div>
@endif

@else
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold">Snapshot partiel</div>
    <div class="card-body">
        <pre class="small mb-0 bg-light p-3 rounded">{{ json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
</div>
@endif
@endsection
