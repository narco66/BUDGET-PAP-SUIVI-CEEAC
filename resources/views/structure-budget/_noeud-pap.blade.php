@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $aEnfants = count($noeud['enfants']) > 0 || ($noeud['type'] === 'activite' && ($noeud['nb_lignes'] ?? 0) > 0);
    $badges = [
        'pilier' => 'primary', 'axe' => 'info', 'produit' => 'success',
        'sous_produit' => 'secondary', 'activite' => 'warning',
    ];
    $badge = $badges[$noeud['type']] ?? 'light';
@endphp
<details @if($noeud['type'] === 'pilier' || $aEnfants) open @endif>
    <summary>
        <div class="sb-node">
            <i class="bi {{ $aEnfants ? 'bi-caret-down-fill' : 'bi-dot' }} text-muted"></i>
            <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }}-emphasis text-capitalize">{{ str_replace('_', ' ', $noeud['type']) }}</span>
            <span class="sb-code">{{ $noeud['code'] }}</span>
            <span class="fw-semibold">{{ \Illuminate\Support\Str::limit($noeud['intitule'], 60) }}</span>
            @if(!empty($noeud['structure']))
                <span class="badge bg-light text-dark">{{ $noeud['structure'] }}</span>
            @endif
            @if(!empty($noeud['projet']))
                <span class="badge bg-light text-muted" title="Projet stratégique">{{ \Illuminate\Support\Str::limit($noeud['projet'], 24) }}</span>
            @endif
            <span class="ms-auto text-end small">
                <span class="badge bg-light text-dark">{{ $noeud['nb_lignes'] ?? 0 }} ligne(s)</span>
                <span class="sb-mt fw-semibold ms-2">{{ $fmt($noeud['total_ouvert'] ?? 0) }}</span>
                <span class="text-muted">ouvert</span>
            </span>
        </div>
    </summary>
    @if($noeud['type'] === 'activite' && !empty($noeud['lignes']))
        <div class="sb-child">
            <table class="table table-sm table-borderless mb-2 small">
                @foreach ($noeud['lignes'] as $l)
                    <tr>
                        <td class="font-monospace"><a href="{{ route('structure-budget.ligne.show', ['ligne' => $l->id, 'exercice' => $exercice?->id]) }}">{{ $l->code }}</a></td>
                        <td>{{ \Illuminate\Support\Str::limit($l->intitule, 40) }}</td>
                        <td class="text-end sb-mt">{{ $fmt($l->montant_ouvert) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @elseif(!empty($noeud['enfants']))
        <div class="sb-child">
            @foreach ($noeud['enfants'] as $enfant)
                @include('structure-budget._noeud-pap', ['noeud' => $enfant, 'exercice' => $exercice])
            @endforeach
        </div>
    @endif
</details>
