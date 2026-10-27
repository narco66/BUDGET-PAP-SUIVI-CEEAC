@php
    $total = $alertes['total_alertes'] ?? ($alertes['en_retard']->count() + $alertes['surconsommation']->count());
    $lienActivite = $lienActivite ?? false;
    $codeActivite = fn (array $a) => $lienActivite && ! empty($a['activite_id'])
        ? '<a href="'.e(route('reporting.cockpit.activite', $a['activite_id'])).'" class="text-decoration-none"><code>'.e($a['code']).'</code></a>'
        : '<code>'.e($a['code']).'</code>';
@endphp

@if($total === 0)
    <div class="alert alert-success border-0 mb-0"><i class="bi bi-check-circle me-1"></i>Aucune alerte de performance sur cet exercice.</div>
@else
    <div class="row g-3">
        @if($alertes['en_retard']->isNotEmpty())
        <div class="col-md-6">
            <h6 class="small fw-bold text-warning"><i class="bi bi-hourglass-split me-1"></i>Activités en retard ({{ $alertes['en_retard']->count() }})</h6>
            <ul class="list-group list-group-flush small">
                @foreach($alertes['en_retard']->take(10) as $a)
                <li class="list-group-item px-0 d-flex justify-content-between">
                    <span title="{{ $a['intitule'] }}">{!! $codeActivite($a) !!} — {{ Str::limit($a['intitule'], 35) }}</span>
                    <span class="badge bg-warning text-dark">{{ $a['taux_physique'] }}%</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
        @if($alertes['surconsommation']->isNotEmpty())
        <div class="col-md-6">
            <h6 class="small fw-bold text-danger"><i class="bi bi-exclamation-octagon me-1"></i>Surconsommation budgétaire ({{ $alertes['surconsommation']->count() }})</h6>
            <ul class="list-group list-group-flush small">
                @foreach($alertes['surconsommation']->take(10) as $a)
                <li class="list-group-item px-0 d-flex justify-content-between">
                    <span title="{{ $a['intitule'] }}">{!! $codeActivite($a) !!} — {{ Str::limit($a['intitule'], 35) }}</span>
                    <span class="badge bg-danger">{{ $a['taux_financier'] }}%</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
@endif
