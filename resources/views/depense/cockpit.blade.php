@extends('layouts.app')
@section('titre', 'Cockpit de la dépense')
@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $g = fn ($k, $d = 0) => $execution[$k] ?? $d;
    $alloue = (float) $g('alloue', $g('ouvert'));
    $pct = fn ($v) => $alloue > 0 ? round((float) $v / $alloue * 100, 1) : 0;
@endphp

@push('styles')<style>.cd-mt{font-variant-numeric:tabular-nums}.cd-step{border-left:4px solid #0a4a8f !important}</style>@endpush

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Cockpit d'exécution de la dépense</h4>
        <span class="text-muted small">Engagement → Liquidation → Ordonnancement → Paiement · Exercice {{ $exercice?->annee ?? '—' }}</span>
    </div>
    <form method="GET"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
    </select></form>
</div>

{{-- KPIs --}}
<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold cd-mt">{{ $fmt($alloue) }}</div><div class="text-muted small">Crédit alloué (XAF)</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-primary cd-mt">{{ $fmt($g('engage')) }}</div><div class="text-muted small">Engagé · {{ $g('taux_engagement') }}%</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold cd-mt">{{ $fmt($g('liquide')) }}</div><div class="text-muted small">Liquidé · {{ $g('taux_liquidation') }}%</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-success cd-mt">{{ $fmt($g('paye')) }}</div><div class="text-muted small">Payé · {{ $g('taux_execution') }}%</div></div></div></div>
</div>

<div class="row g-3 mt-1">
    {{-- Chaîne d'exécution --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-semibold mb-3"><i class="bi bi-bar-chart-steps me-1"></i>Progression de la chaîne (% du crédit alloué)</h6>
            @foreach ([['Engagé', $g('engage'), 'bg-primary'], ['Liquidé', $g('liquide'), 'bg-info'], ['Ordonnancé', $g('ordonnance'), 'bg-warning'], ['Payé', $g('paye'), 'bg-success']] as [$lib, $val, $cls])
            <div class="d-flex justify-content-between small mb-1"><span>{{ $lib }}</span><span class="cd-mt">{{ $fmt($val) }}</span></div>
            <div class="progress mb-3" style="height:1.1rem"><div class="progress-bar {{ $cls }}" style="width:{{ $pct($val) }}%">{{ $pct($val) }}%</div></div>
            @endforeach
            <div class="row text-center mt-2">
                <div class="col"><div class="fw-bold text-warning cd-mt">{{ $fmt($g('reste_a_engager')) }}</div><div class="text-muted small">Reste à engager</div></div>
                <div class="col"><div class="fw-bold text-danger cd-mt">{{ $fmt($g('reste_a_payer')) }}</div><div class="text-muted small">Reste à payer (ordonnancé non payé)</div></div>
                <div class="col"><div class="fw-bold cd-mt">{{ $fmt($g('disponible')) }}</div><div class="text-muted small">Disponible</div></div>
            </div>
        </div></div>
    </div>

    {{-- Engagements par statut + conformité --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-1"></i>Engagements par statut</div>
            <div class="card-body p-0"><table class="table table-sm mb-0">
                <tbody>
                @forelse ($parStatut as $s)
                    <tr><td class="text-capitalize small">{{ $s->statut }}</td><td class="text-end"><span class="badge bg-secondary">{{ $s->nombre }}</span></td>
                        <td class="text-end small cd-mt">{{ $fmt($s->montant) }}</td></tr>
                @empty<tr><td colspan="3" class="text-center text-muted py-3">Aucun engagement.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
        @if($audit)
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span><i class="bi bi-shield-check me-1"></i>Conformité de la chaîne</span>
            <span class="badge {{ $audit['score_conformite'] >= 80 ? 'bg-success' : ($audit['score_conformite'] >= 50 ? 'bg-warning' : 'bg-danger') }}">{{ $audit['score_conformite'] }}/100</span></div>
            <div class="card-body py-2 small">
                <div class="d-flex justify-content-between"><span>Anomalies détectées</span><strong>{{ $audit['anomalies_total'] }}</strong></div>
                @foreach (($audit['par_gravite'] ?? []) as $gravite => $n)
                    <div class="d-flex justify-content-between"><span class="text-capitalize text-muted">Gravité {{ $gravite }}</span><span>{{ $n }}</span></div>
                @endforeach
                <a href="{{ route('depense.export.consolide', ['exercice' => $exercice?->id]) }}" class="btn btn-sm btn-outline-secondary w-100 mt-2"><i class="bi bi-download me-1"></i>Journal consolidé</a>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Exécution par tâche --}}
<div class="card border-0 shadow-sm mt-3"><div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-1"></i>Exécution par tâche (top 12 par montant engagé)</div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Tâche</th><th class="text-end">Engagé</th><th class="text-end">Liquidé</th><th class="text-end">Ordonnancé</th><th class="text-end">Payé</th></tr></thead>
        <tbody>
        @forelse ($parTache as $t)
            <tr>
                <td class="small">{{ $t->tache_code }} — {{ \Illuminate\Support\Str::limit($t->tache_intitule, 36) }}</td>
                <td class="text-end small text-primary cd-mt">{{ $fmt($t->montant_engage) }}</td>
                <td class="text-end small cd-mt">{{ $fmt($t->montant_liquide) }}</td>
                <td class="text-end small cd-mt">{{ $fmt($t->montant_ordonnance) }}</td>
                <td class="text-end small text-success cd-mt">{{ $fmt($t->montant_paye) }}</td>
            </tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-4">Aucune dépense ventilée par tâche.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
