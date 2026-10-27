@extends('layouts.app')
@section('titre', 'Intégrité des journaux')

@section('contenu')
@use('App\Support\Horodatage')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-fingerprint me-2"></i>Intégrité des journaux</h4>
        <span class="text-muted small">{{ number_format($total, 0, ',', ' ') }} événements · {{ number_format($nonScelles, 0, ',', ' ') }} en attente de scellement · {{ number_format($sansEmpreinte, 0, ',', ' ') }} antérieurs au module (protégés par scellement)</span>
    </div>
    @if ($peutExecuter)
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('audit.integrite.sceller') }}">@csrf<button class="btn btn-sm btn-outline-primary"><i class="bi bi-link-45deg me-1"></i>Sceller</button></form>
            <form method="POST" action="{{ route('audit.integrite.verifier') }}">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-shield-check me-1"></i>Vérifier l'intégrité</button></form>
        </div>
    @endif
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>Chaque événement porte une empreinte SHA-256 de sa forme canonique ; les lots sont chaînés
    (H<sub>i</sub> = SHA-256(H<sub>i-1</sub> | id | empreinte<sub>i</sub>)), la tête est signée et déposée hors base.
    Cette protection détecte une modification, une suppression ou une insertion a posteriori ; elle ne remplace pas un dépôt externe immuable
    face à un administrateur disposant de tous les privilèges.
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body">
        <h6 class="fw-bold small text-uppercase text-muted">Contrôles d'intégrité</h6>
        <div class="table-responsive"><table class="table table-sm small align-middle mb-0">
            <thead class="table-light"><tr><th>Référence</th><th>Exécuté le</th><th>Résultat</th><th>Événements</th><th>Lots</th><th>Horloge</th></tr></thead>
            <tbody>
            @forelse ($controles as $c)
                <tr>
                    <td>{{ $c->reference }}<div class="text-muted">{{ $c->declenchement }}</div></td>
                    <td class="text-nowrap">{{ Horodatage::local($c->debut_le) }}</td>
                    <td><span class="badge text-bg-{{ ['conforme' => 'success', 'anomalie' => 'danger'][$c->resultat] ?? 'secondary' }}">{{ $c->resultat }}</span>
                        @foreach (array_slice($c->anomalies ?? [], 0, 3) as $a)<div class="text-danger">{{ $a['detail'] }}@isset($a['id']) (n° {{ $a['id'] }})@endisset</div>@endforeach</td>
                    <td>{{ $c->evenements_verifies }}</td>
                    <td>{{ $c->scellements_verifies }}</td>
                    <td>{{ $c->horloge['statut'] ?? '—' }}@isset($c->horloge['ecart_base_s'])<div class="text-muted">écart {{ $c->horloge['ecart_base_s'] }} s · NTP {{ $c->horloge['ntp'] ?? '—' }}</div>@endisset</td>
                </tr>
            @empty <tr><td colspan="6" class="text-center text-muted py-3">Aucun contrôle exécuté.</td></tr> @endforelse
            </tbody>
        </table></div>
    </div></div></div>
    <div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body">
        <h6 class="fw-bold small text-uppercase text-muted">Scellements</h6>
        <div class="table-responsive"><table class="table table-sm small align-middle mb-0">
            <thead class="table-light"><tr><th>Référence</th><th>Événements</th><th>Tête</th><th>Dépôt</th></tr></thead>
            <tbody>
            @forelse ($scellements as $s)
                <tr>
                    <td>{{ $s->reference }}<div class="text-muted">{{ Horodatage::local($s->scelle_le, 'd/m/Y H:i') }}</div></td>
                    <td>{{ $s->de_id }} → {{ $s->a_id }}<div class="text-muted">{{ $s->nombre }}</div></td>
                    <td><code class="small">{{ substr($s->empreinte_tete, 0, 12) }}…</code></td>
                    <td>{!! $s->depot ? '<i class="bi bi-check2 text-success"></i>' : '<i class="bi bi-x text-danger"></i>' !!}</td>
                </tr>
            @empty <tr><td colspan="4" class="text-center text-muted py-3">Aucun scellement.</td></tr> @endforelse
            </tbody>
        </table></div>
    </div></div></div>
</div>
@endsection
