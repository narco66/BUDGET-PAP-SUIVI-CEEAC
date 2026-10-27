{{--
    Suivi de la chaîne de dépense (EB → ENG → LIQ → ORD → PAY) : où en est le dossier, qui doit agir,
    et pour chaque étape ses visas. Données : DepenseDossierService (timeline + situation).
    Paramètres : $timeline, $situation, $cloture (facultatif), $compact (bool, fiche EB).
--}}
@php
    $compact = $compact ?? false;
    $couleurs = ['brouillon' => 'secondary', 'soumis' => 'warning', 'a_corriger' => 'warning', 'valide' => 'success', 'vise' => 'success',
        'rejete' => 'danger', 'converti' => 'info', 'emis' => 'info', 'execute' => 'success', 'annule' => 'dark', 'en_attente_arbitrage' => 'danger'];
    $icones = ['valide' => 'bi-check-circle-fill text-success', 'rejete' => 'bi-x-circle-fill text-danger', 'renvoye' => 'bi-arrow-return-left text-warning', 'en_attente' => 'bi-hourglass-split text-secondary'];
    $fmtMontant = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $date = fn ($d) => $d ? \App\Support\Horodatage::local($d, 'd/m/Y H:i') : null;
    $enAttente = $situation['en_attente'] ?? [];
@endphp

{{-- Situation actuelle --}}
<div class="alert {{ empty($enAttente) ? (!empty($cloture['soldee']) ? 'alert-success' : 'alert-light border') : 'alert-primary' }} mb-3">
    @if (!empty($cloture['soldee']))
        <div class="fw-semibold"><i class="bi bi-check2-all me-1"></i>Chaîne soldée : le dossier est entièrement payé.</div>
    @elseif (empty($enAttente))
        @php $d = $situation['derniere'] ?? null; @endphp
        <div class="fw-semibold"><i class="bi bi-info-circle me-1"></i>Aucune action en attente.</div>
        @if ($d)<div class="small">Dernière étape : {{ $d['label'] }} <strong>{{ $d['numero'] }}</strong> — {{ $d['statut_libelle'] }}.</div>@endif
    @else
        <div class="fw-semibold mb-1"><i class="bi bi-geo-alt-fill me-1"></i>Où en est le dossier ?</div>
        @foreach ($enAttente as $e)
            @php $a = $e['en_attente']; @endphp
            <div class="small {{ !$loop->last ? 'mb-1' : '' }}">
                <strong>{{ $e['label'] }} {{ $e['numero'] }}</strong> — {{ $a['libelle'] }}<br>
                <i class="bi bi-person-fill me-1"></i>À traiter par : <strong>{{ $a['role'] }}</strong>
                @if (!empty($a['titulaires'])) ({{ implode(', ', $a['titulaires']) }})@endif
                @if ($a['pris_en_charge_par']) · pris en charge par {{ $a['pris_en_charge_par'] }}@endif
                · en attente depuis le {{ $date($a['recue_le']) }}
                @if ($a['en_retard'])<span class="badge bg-danger ms-1">En retard</span>@endif
                · <a href="{{ route('taches.show', $a['tache']) }}" class="alert-link">tâche {{ $a['tache']->reference }}</a>
            </div>
        @endforeach
    @endif
    @if (!empty($cloture) && empty($cloture['soldee']))
        <div class="small text-muted mt-1">Reste à liquider : {{ $fmtMontant($cloture['restant_a_liquider'] ?? 0) }} XAF · Reste à payer : {{ $fmtMontant($cloture['restant_a_payer'] ?? 0) }} XAF</div>
    @endif
</div>

{{-- Frise de la chaîne --}}
<ol class="list-unstyled mb-0">
@foreach ($timeline as $etape)
    <li class="d-flex {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
        <div class="me-3 text-center" style="min-width:7.5rem">
            <span class="badge rounded-pill bg-{{ $couleurs[$etape['statut']] ?? 'secondary' }} w-100 text-wrap">{{ $etape['label'] }}</span>
        </div>
        <div class="flex-grow-1">
            <div class="d-flex justify-content-between flex-wrap gap-1">
                <strong>@if ($etape['route'])<a href="{{ $etape['route'] }}">{{ $etape['numero'] }}</a>@else{{ $etape['numero'] }}@endif</strong>
                <span class="small">{{ $fmtMontant($etape['montant']) }} XAF</span>
            </div>
            <div class="small text-muted">{{ $etape['statut_libelle'] }}@if ($etape['date']) · {{ $etape['date'] instanceof \DateTimeInterface ? $etape['date']->format('d/m/Y') : $etape['date'] }}@endif</div>

            @if (!empty($etape['visas']))
                <ul class="list-unstyled small mb-0 mt-1">
                    @foreach ($etape['visas'] as $v)
                        <li><i class="bi {{ $icones[$v['statut']] ?? 'bi-circle text-secondary' }} me-1"></i>{{ $v['libelle'] }}
                            @if ($v['acteur'])<span class="text-muted">— {{ $v['acteur'] }}, {{ $date($v['date']) }}</span>
                            @elseif ($v['statut'] === 'en_attente')<span class="text-muted">— en attente ({{ $v['role'] }})</span>@endif
                            @if (!$compact && $v['observation'] && $v['statut'] !== 'valide')<div class="ms-4 fst-italic text-muted">« {{ $v['observation'] }} »</div>@endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($etape['en_attente'])
                @php $a = $etape['en_attente']; @endphp
                <div class="small mt-1 p-1 px-2 rounded bg-primary-subtle">
                    <i class="bi bi-hourglass-split me-1"></i><strong>En attente :</strong> {{ $a['libelle'] }} — <strong>{{ $a['role'] }}</strong>
                    @if (!empty($a['titulaires']))({{ implode(', ', $a['titulaires']) }})@endif
                </div>
            @endif

            @if (!$compact && !empty($etape['services_faits']) && count($etape['services_faits']))
                <ul class="small mb-0 mt-1">
                    @foreach ($etape['services_faits'] as $sf)
                        <li>{{ \App\Models\ServiceFait::TYPES[$sf->type] ?? $sf->type }} — {{ $sf->constatation ?? $sf->reference }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </li>
@endforeach
</ol>
