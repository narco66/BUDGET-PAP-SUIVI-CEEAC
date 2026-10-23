@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $s = $noeud['structure'];
    $aEnfants = count($noeud['enfants']) > 0;
    $typeBadge = [
        'presidence' => 'dark', 'vice_presidence' => 'dark', 'secretariat_general' => 'primary',
        'commissariat' => 'primary', 'departement' => 'info', 'direction' => 'success',
        'service' => 'secondary', 'cellule' => 'secondary',
    ][$s->type] ?? 'secondary';
@endphp
<details @if($aEnfants) open @endif>
    <summary>
        <div class="sb-node">
            <i class="bi {{ $aEnfants ? 'bi-caret-down-fill' : 'bi-dot' }} text-muted"></i>
            <span class="badge bg-{{ $typeBadge }}-subtle text-{{ $typeBadge }}-emphasis text-capitalize">{{ str_replace('_', ' ', $s->type) }}</span>
            <span class="sb-code">{{ $s->code }}</span>
            <span class="fw-semibold">{{ $s->sigle ?: $s->libelle }}</span>
            <span class="ms-auto text-end small">
                <span class="badge bg-light text-dark">{{ $noeud['nb_total'] }} ligne(s)</span>
                <span class="sb-mt fw-semibold ms-2">{{ $fmt($noeud['total_ouvert']) }}</span>
                <span class="text-muted">ouvert · </span>
                <span class="sb-mt text-primary">{{ $fmt($noeud['total_engage']) }}</span>
                <span class="text-muted">engagé</span>
                @if($noeud['nb_propre'] > 0)
                    <a href="{{ route('structure-budget.structure', ['structure' => $s->id, 'exercice' => $exercice?->id]) }}" class="ms-2 text-decoration-none" title="Voir les {{ $noeud['nb_propre'] }} ligne(s) propres"><i class="bi bi-eye"></i></a>
                @endif
            </span>
        </div>
    </summary>
    @if($aEnfants)
        <div class="sb-child">
            @foreach ($noeud['enfants'] as $enfant)
                @include('structure-budget._noeud', ['noeud' => $enfant, 'exercice' => $exercice])
            @endforeach
        </div>
    @endif
</details>
