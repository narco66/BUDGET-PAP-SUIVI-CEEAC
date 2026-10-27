@extends('layouts.app')
@section('titre', 'Événement d\'audit')

@section('contenu')
@use('App\Support\Horodatage')
@php
    $avant = $j->avant ?? [];
    $apres = $j->apres ?? [];
    $champs = collect(array_keys($avant))->merge(array_keys($apres))->unique()->values();
    $val = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_bool($v) ? ($v ? 'oui' : 'non') : ($v ?? '∅'));
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-lock me-2"></i><code>{{ $j->code_evenement ?? strtoupper($j->action) }}</code></h4>
        <span class="text-muted small">Événement n° {{ $j->id }} @if($j->uuid)· {{ $j->uuid }}@endif</span>
    </div>
    <a href="{{ url()->previous() === url()->current() ? route('audit.index') : url()->previous() }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold text-uppercase small text-muted">Identification</h6>
            <dl class="row small mb-0">
                <dt class="col-5">Catégorie</dt><dd class="col-7">@if($j->categorie){{ \App\Models\JournalAudit::CATEGORIES[$j->categorie] ?? $j->categorie }}@else<span class="fst-italic text-muted">{{ \App\Models\JournalAudit::CATEGORIES[\App\Support\AuditLogger::categorie($j->action)] }} (déduite)</span>@endif</dd>
                <dt class="col-5">Module</dt><dd class="col-7">@if($j->module){{ $j->module }}@else<span class="fst-italic text-muted">{{ \App\Support\AuditLogger::moduleDuType($j->auditable_type) ?? \App\Support\AuditLogger::module($j->action, null) }} (déduit)</span>@endif</dd>
                <dt class="col-5">Action</dt><dd class="col-7">{{ $j->action }}</dd>
                <dt class="col-5">Résultat</dt><dd class="col-7">{{ \App\Models\JournalAudit::RESULTATS[$j->resultat] ?? '—' }}</dd>
                <dt class="col-5">Gravité</dt><dd class="col-7">{{ \App\Models\JournalAudit::GRAVITES[$j->gravite] ?? '—' }}</dd>
                <dt class="col-5">Motif</dt><dd class="col-7">{{ $j->motif ?? '—' }}</dd>
            </dl>
            <h6 class="fw-bold text-uppercase small text-muted mt-3">Temps</h6>
            <dl class="row small mb-0">
                <dt class="col-5">Survenance (UTC)</dt><dd class="col-7"><code>{{ Horodatage::utc($j->instant()) ?? '—' }}</code></dd>
                <dt class="col-5">Affichage local</dt><dd class="col-7">{{ Horodatage::local($j->instant(), 'd/m/Y H:i:s.v') }} ({{ Horodatage::fuseau() }})</dd>
                <dt class="col-5">Inscription au journal</dt><dd class="col-7">{{ $j->occurred_at && $j->instantEnregistrement() ? Horodatage::local($j->instantEnregistrement(), 'd/m/Y H:i:s.v') : 'non disponible (antérieur au module)' }}</dd>
                <dt class="col-5">Date d'effet</dt><dd class="col-7">{{ $j->effective_at ? Horodatage::local($j->effective_at) : '—' }}</dd>
                <dt class="col-5">Précision</dt><dd class="col-7">{{ $j->occurred_at ? 'microseconde (horloge du serveur d\'application, synchronisée NTP)' : 'seconde (événement antérieur au module)' }}</dd>
            </dl>
            <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Horodatage technique du serveur, distinct d'un horodatage électronique qualifié.</p>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold text-uppercase small text-muted">Acteur</h6>
            <dl class="row small mb-0">
                <dt class="col-5">Auteur réel</dt><dd class="col-7">{{ $j->user_nom ?? 'Système' }} @if($j->user_id)<a href="{{ route('audit.utilisateur', $j->user_id) }}" class="ms-1 small">historique</a>@endif</dd>
                <dt class="col-5">Fonction au moment de l'acte</dt><dd class="col-7">{{ $j->role ?? '—' }}</dd>
                <dt class="col-5">Unité</dt><dd class="col-7">{{ $j->structure?->code ?? '—' }}</dd>
                <dt class="col-5">Pour le compte de</dt><dd class="col-7">{{ $j->pour_compte_de ? ($j->titulaire?->name ?? '#'.$j->pour_compte_de).' (délégation / intérim)' : '—' }}</dd>
                <dt class="col-5">Provenance</dt><dd class="col-7">{{ $j->source ?? '—' }} · {{ $j->ip ?? '—' }}</dd>
            </dl>
            <h6 class="fw-bold text-uppercase small text-muted mt-3">Métier</h6>
            <dl class="row small mb-0">
                <dt class="col-5">Objet</dt><dd class="col-7">{{ $j->libelleObjet() ?? '—' }}
                    @if($j->auditable_type)<a href="{{ route('audit.dossier', [class_basename($j->auditable_type), $j->auditable_id]) }}" class="ms-1 small">chronologie du dossier</a>@endif</dd>
                <dt class="col-5">Instance de workflow</dt><dd class="col-7">{{ $j->workflow_instance_id ?? '—' }}</dd>
                <dt class="col-5">Tâche</dt><dd class="col-7">@if($j->workflow_tache_id)<a href="{{ route('taches.show', $j->workflow_tache_id) }}">#{{ $j->workflow_tache_id }}</a>@else — @endif</dd>
                <dt class="col-5">Corrélation</dt><dd class="col-7">@if($j->correlation_id)<a href="{{ route('audit.index', ['correlation_id' => $j->correlation_id]) }}"><code>{{ $j->correlation_id }}</code></a>@else — @endif</dd>
                <dt class="col-5">Requête</dt><dd class="col-7"><code class="small">{{ $j->request_id ?? '—' }}</code></dd>
            </dl>
        </div></div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h6 class="fw-bold text-uppercase small text-muted">Modifications et données</h6>
            @if ($champs->isEmpty())
                <p class="text-muted small mb-0">Aucune valeur enregistrée.</p>
            @else
                <div class="table-responsive"><table class="table table-sm small mb-0">
                    <thead class="table-light"><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead>
                    <tbody>
                    @foreach ($champs as $c)
                        @php $a = $avant[$c] ?? null; $b = $apres[$c] ?? null; @endphp
                        <tr @class(['table-warning' => array_key_exists($c, $avant) && array_key_exists($c, $apres) && $a !== $b])>
                            <td class="fw-semibold">{{ $c }}</td>
                            <td class="text-break">{{ array_key_exists($c, $avant) ? $val($a) : '' }}</td>
                            <td class="text-break">{{ array_key_exists($c, $apres) ? $val($b) : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold text-uppercase small text-muted">Intégrité</h6>
            <dl class="row small mb-0">
                <dt class="col-5">Empreinte inscrite</dt><dd class="col-7"><code class="small text-break">{{ $j->empreinte ?? '— (antérieur au module)' }}</code></dd>
                <dt class="col-5">Empreinte recalculée</dt><dd class="col-7"><code class="small text-break">{{ $j->empreinteCalculee() }}</code></dd>
                <dt class="col-5">État</dt><dd class="col-7">
                    @if ($empreinteValide === true)<span class="badge text-bg-success">Conforme</span>
                    @elseif ($empreinteValide === false)<span class="badge text-bg-danger">Contenu modifié après inscription</span>
                    @else<span class="badge text-bg-secondary">Vérifiable par scellement uniquement</span>@endif</dd>
                <dt class="col-5">Scellement</dt><dd class="col-7">{{ $scellement ? $scellement->reference.' (événements '.$scellement->de_id.' à '.$scellement->a_id.')' : 'Non encore scellé' }}</dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold text-uppercase small text-muted">Événements corrélés @if($j->correlation_id)<span class="text-muted">({{ $correles->count() }})</span>@endif</h6>
            @forelse ($correles as $c)
                <div class="small d-flex justify-content-between border-bottom py-1 @if($c->id === $j->id) fw-bold @endif">
                    <span><a href="{{ route('audit.show', $c) }}"><code class="small">{{ $c->code_evenement ?? $c->action }}</code></a> · {{ $c->user_nom ?? 'Système' }}</span>
                    <span class="text-muted text-nowrap">{{ Horodatage::local($c->instant(), 'd/m H:i:s') }}</span>
                </div>
            @empty
                <p class="text-muted small mb-0">Aucune corrélation de chaîne de dépense.</p>
            @endforelse
        </div></div>
    </div>
</div>
@endsection
