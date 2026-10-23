{{-- Bande Suivi-Évaluation pour le cockpit exécutif (additive, lecture seule). --}}
@php
    $se = $suiviEval ?? [];
    $eval = $se['evaluation'] ?? null;
    $pap = $se['pap'] ?? [];
    $feux = $se['feux'] ?? ['vert' => 0, 'orange' => 0, 'rouge' => 0];
    $score = $eval?->score_global;
    $niveau = $eval?->niveau;
    $niveauCls = ['satisfaisant' => 'success', 'partiel' => 'warning', 'insuffisant' => 'danger'][$niveau] ?? 'secondary';
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
            <h6 class="fw-semibold mb-0"><i class="bi bi-clipboard-data me-1"></i>Performance & Suivi-Évaluation</h6>
            @can('suivi_evaluation.view')<a href="{{ route('suivi-evaluation.index') }}" class="small text-decoration-none">Ouvrir le hub S&E <i class="bi bi-arrow-right"></i></a>@endcan
        </div>
        <div class="row g-3 text-center">
            <div class="col-6 col-lg-3">
                <div class="fs-4 fw-bold">{{ $score !== null ? number_format((float) $score, 1).'%' : '—' }}</div>
                <div class="small text-muted">Score S&E global @if($niveau)<span class="badge bg-{{ $niveauCls }}-subtle text-{{ $niveauCls }}-emphasis text-capitalize">{{ $niveau }}</span>@endif</div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="fs-4 fw-bold">{{ number_format((float) ($pap['taux_physique'] ?? 0), 1) }}%</div>
                <div class="small text-muted">Réalisation physique PAP</div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="fs-4 fw-bold">{{ number_format((float) ($pap['taux_financier'] ?? 0), 1) }}%</div>
                <div class="small text-muted">Réalisation financière PAP</div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="fs-5 fw-bold">
                    <span class="text-success">●{{ $feux['vert'] ?? 0 }}</span>
                    <span class="text-warning ms-1">●{{ $feux['orange'] ?? 0 }}</span>
                    <span class="text-danger ms-1">●{{ $feux['rouge'] ?? 0 }}</span>
                </div>
                <div class="small text-muted">Feux indicateurs (V/O/R)</div>
            </div>
        </div>
    </div>
</div>
