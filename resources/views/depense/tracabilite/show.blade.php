@extends('layouts.app')
@section('titre', 'Traçabilité '.$dossier['focal']['numero'])
@php
$fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
$dt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y H:i') : '—';
$depuis = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale('fr')->diffForHumans(['parts' => 2]) : '—';
$f = $dossier['focal'];
$s = $dossier['situation'];
$sd = $dossier['situation_dossier'];
$etats = ['en_attente' => ['En attente', 'primary'], 'en_cours' => ['Pris en charge', 'info'], 'retourne' => ['Retourné pour correction', 'warning'],
    'rejete' => ['Rejeté', 'danger'], 'suspendu' => ['Suspendu', 'secondary'], 'bloque' => ['Bloqué', 'danger'], 'termine' => ['Aucune action attendue', 'success']];
$prog = ['fait' => ['success', 'check-circle-fill'], 'en_cours' => ['primary', 'arrow-right-circle-fill'], 'a_venir' => ['secondary', 'circle'], 'rejete' => ['danger', 'x-circle-fill']];
$gravites = ['bloquante' => 'danger', 'majeure' => 'warning', 'avertissement' => 'secondary'];
$onglets = ['synthese' => 'Synthèse', 'workflow' => 'Circuit et délais', 'historique' => 'Historique', 'chaine' => 'Dossiers liés',
    'finances' => 'Finances', 'documents' => 'Documents', 'anomalies' => 'Blocages ('.$dossier['anomalies']->count().')'];
@endphp
@section('contenu')
{{-- En-tête --}}
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <a href="{{ route('depense.tracabilite.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Recherche</a>
        <h4 class="fw-bold mb-1 mt-1"><i class="bi bi-diagram-3 me-1"></i>{{ $f['processus_libelle'] }} <code>{{ $f['numero'] }}</code></h4>
        <span class="text-muted">{{ \Illuminate\Support\Str::limit($f['objet']->objet ?? $f['objet']->beneficiaire ?? '', 120) }}</span>
        <div class="mt-1">
            <span class="badge bg-light text-dark border">{{ $f['statut_libelle'] }}</span>
            <span class="badge bg-light text-dark border">{{ $fmt($f['montant']) }} XAF</span>
            @if($dossier['besoin'])<span class="badge bg-light text-dark border">Origine : {{ $dossier['besoin']->numero }}</span>@endif
            @if($dossier['termine'])<span class="badge bg-success">Chaîne soldée</span>@endif
        </div>
    </div>
    @if($f['url'])<a href="{{ $f['url'] }}" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i>Ouvrir la fiche</a>@endif
</div>

{{-- Vue graphique de la chaîne --}}
<div class="card border-0 shadow-sm mb-3"><div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
    @foreach($dossier['progression'] as $p => $etat)
        <div class="text-center flex-fill">
            <i class="bi bi-{{ $prog[$etat][1] }} text-{{ $prog[$etat][0] }} fs-3"></i>
            <div class="fw-bold small">{{ $p }}</div>
            <div class="small text-muted">{{ $dossier['noeuds']->where('processus', $p)->count() }} dossier(s)</div>
        </div>
        @if(! $loop->last)<i class="bi bi-chevron-right text-muted"></i>@endif
    @endforeach
</div></div>

{{-- Situation actuelle --}}
<div class="card border-0 shadow-sm mb-3 border-start border-4 border-{{ $etats[$s['etat']][1] }}">
    <div class="card-header bg-white fw-bold d-flex justify-content-between">
        <span><i class="bi bi-geo-alt me-1"></i>Situation actuelle — <code>{{ $sd['numero'] }}</code></span>
        <span class="badge bg-{{ $etats[$s['etat']][1] }}">{{ $etats[$s['etat']][0] }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small text-uppercase">Où se trouve le dossier</div>
                @if($s['etape'])
                    <div class="fw-bold">{{ $s['etape']['libelle'] }}</div>
                    <div class="small">{{ $s['circuit'] ?? 'Préparation' }} · étape {{ $s['etape']['ordre'] }}</div>
                    <div class="small text-muted">Depuis le {{ $dt($s['etape']['entree_le']) }} ({{ $depuis($s['etape']['entree_le']) }})</div>
                @else
                    <div class="fw-bold">{{ $sd['statut_libelle'] }}</div>
                    <div class="small text-muted">Aucune étape de circuit en cours.</div>
                @endif
            </div>
            <div class="col-md-4">
                <div class="text-muted small text-uppercase">Dernier traitement</div>
                @if($d = $s['dernier'])
                    <div class="fw-bold">@if($d['automatique'])<i class="bi bi-cpu me-1"></i>@endif{{ $d['nom'] }}</div>
                    <div class="small">{{ collect([$d['fonction'], $d['role'], $d['structure']])->filter()->implode(' · ') }}</div>
                    <div class="small">{{ $d['action'] }} — {{ $dt($d['date']) }}</div>
                    @if($d['commentaire'])<div class="small fst-italic">« {{ $d['commentaire'] }} »</div>@endif
                    @if($d['pour_compte_de'])<div class="small text-muted">Déclenchée par l'action de {{ $d['pour_compte_de'] }}</div>@endif
                    @if($h = $d['dernier_humain'] ?? null)<div class="small text-muted">Dernier acteur humain : {{ $h['nom'] }} ({{ $h['action'] }}, {{ $dt($h['date']) }})</div>@endif
                @else
                    <div class="text-muted">Aucun traitement enregistré.</div>
                @endif
            </div>
            <div class="col-md-4">
                <div class="text-muted small text-uppercase">Qui doit agir maintenant</div>
                @if($a = $s['attendu'])
                    @if($a['manquante'])
                        <div class="fw-bold text-danger"><i class="bi bi-exclamation-octagon me-1"></i>Affectation manquante — Intervention administrative requise</div>
                        <div class="small">Rôle requis : {{ $a['role'] }}</div>
                        @foreach($a['exclus'] as $ex)<div class="small text-muted">{{ $ex['nom'] }} écarté : {{ $ex['motif'] }}</div>@endforeach
                    @elseif($a['nominatif'])
                        <div class="fw-bold">{{ $a['nominatif']['nom'] }}</div>
                        <div class="small">{{ $a['nominatif']['fonction'] ?? $a['role'] }} · pris en charge</div>
                    @else
                        <div class="fw-bold">{{ $a['role'] }}</div>
                        <div class="small">{{ collect($a['titulaires'])->map(fn ($t) => $t['nom'].($t['fonction'] ? ' ('.$t['fonction'].')' : ''))->take(4)->implode(', ') }}@if(count($a['titulaires']) > 4) … @endif</div>
                        @if($a['regle'])<div class="small text-muted">{{ $a['regle'] }}</div>@endif
                    @endif
                    <div class="small mt-1"><strong>Action attendue :</strong> {{ $a['action'] }}</div>
                    <div class="small {{ $a['en_retard'] ? 'text-danger fw-bold' : 'text-muted' }}">
                        En attente depuis {{ $depuis($a['depuis']) }}@if($a['echeance']) · échéance {{ $dt($a['echeance']) }}@endif @if($a['en_retard'])(en retard)@endif
                    </div>
                    @if($a['tache'])<a href="{{ route('taches.show', $a['tache']) }}" class="small">Tâche {{ $a['tache_statut'] }} dans « Mes Tâches »</a>@endif
                @else
                    <div class="text-muted">Aucune action attendue.</div>
                @endif
            </div>
        </div>
        @if($s['prochain'])
            <hr>
            <div class="text-muted small text-uppercase mb-1">Ensuite</div>
            @foreach($s['prochain'] as $p)
                <div class="small"><i class="bi bi-arrow-return-right me-1"></i><strong>{{ $p['processus'] }}</strong> — {{ $p['etape'] }} : {{ $p['role'] }} <span class="text-muted">({{ $p['condition'] }})</span></div>
            @endforeach
        @endif
        @if($dossier['en_attente']->count() > 1)
            <hr>
            <div class="text-muted small text-uppercase mb-1">Autres actions attendues dans la chaîne</div>
            @foreach($dossier['en_attente']->skip(1) as $n)
                <div class="small"><a href="{{ $n['url_tracabilite'] }}"><code>{{ $n['numero'] }}</code></a> — {{ $n['situation']['attendu']['action'] }} : {{ $n['situation']['attendu']['manquante'] ? 'Affectation manquante' : ($n['situation']['attendu']['nominatif']['nom'] ?? $n['situation']['attendu']['role']) }}</div>
            @endforeach
        @endif
    </div>
</div>

{{-- Onglets --}}
<ul class="nav nav-tabs" role="tablist">
    @foreach($onglets as $cle => $libelle)
        <li class="nav-item"><button class="nav-link @if($onglet === $cle) active @endif" data-bs-toggle="tab" data-bs-target="#t-{{ $cle }}" type="button">{{ $libelle }}</button></li>
    @endforeach
</ul>
<div class="tab-content card border-0 shadow-sm border-top-0 rounded-0 rounded-bottom mb-4">
    {{-- Synthèse --}}
    <div class="tab-pane fade @if($onglet === 'synthese') show active @endif p-3" id="t-synthese">
        <div class="row g-3">
            @foreach(['besoin' => 'Besoin estimé', 'engage' => 'Engagé', 'liquide' => 'Liquidé', 'ordonnance' => 'Ordonnancé', 'paye' => 'Payé', 'reste_a_payer' => 'Reste à payer'] as $k => $l)
                @if($dossier['finances'][$k] !== null)
                <div class="col-6 col-md-2"><div class="text-muted small">{{ $l }}</div><div class="fw-bold">{{ $fmt($dossier['finances'][$k]) }}</div></div>
                @endif
            @endforeach
        </div>
        @if($s['restantes'])
            <h6 class="fw-bold mt-3">Étapes restantes du circuit en cours</h6>
            <ol class="small mb-0">@foreach($s['restantes'] as $r)<li value="{{ $r['ordre'] }}">{{ $r['libelle'] }} — {{ $r['role'] }}</li>@endforeach</ol>
        @endif
        @if($dossier['anomalies']->isNotEmpty())
            <div class="alert alert-warning mt-3 mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i>{{ $dossier['anomalies']->count() }} blocage(s) ou incohérence(s) détecté(s) : voir l'onglet « Blocages ».</div>
        @endif
    </div>

    {{-- Circuit et délais --}}
    <div class="tab-pane fade @if($onglet === 'workflow') show active @endif p-3" id="t-workflow">
        @foreach($dossier['noeuds'] as $n)
            <h6 class="fw-bold @if(! $loop->first) mt-4 @endif">{{ $n['processus'] }} <code>{{ $n['numero'] }}</code> <span class="badge bg-light text-dark border">{{ $n['statut_libelle'] }}</span></h6>
            @if(empty($n['etapes']))
                <div class="small text-muted">Aucun circuit démarré.</div>
            @else
            <div class="table-responsive"><table class="table table-sm align-middle small mb-0">
                <thead class="table-light"><tr><th>Circuit</th><th>#</th><th>Étape</th><th>Rôle</th><th>Statut</th><th>Acteur</th><th>Reçue le</th><th>Traitée le</th><th>Durée</th><th>J. ouvrés</th><th>Échéance</th></tr></thead>
                <tbody>
                @foreach($n['etapes'] as $e)
                    <tr class="@if($e['statut'] === 'actuelle') table-primary @elseif($e['retard']) table-warning @endif">
                        <td>{{ $e['circuit'] }}@if($e['instance_statut'] !== 'en_cours' && $e['instance_statut'] !== 'valide') <span class="badge bg-secondary">{{ $e['instance_statut'] }}</span>@endif</td>
                        <td>{{ $e['ordre'] }}</td><td>{{ $e['libelle'] }}</td><td>{{ $e['role'] }}</td>
                        <td>{{ ['actuelle' => 'Actuelle', 'future' => 'À venir', 'non_atteinte' => 'Non atteinte', 'valide' => 'Validée', 'rejete' => 'Rejetée', 'renvoye' => 'Renvoyée'][$e['statut']] ?? $e['statut'] }}</td>
                        <td>{{ $e['acteur'] ?? '—' }}@if($e['acteur_fonction'])<div class="text-muted">{{ $e['acteur_fonction'] }}</div>@endif</td>
                        <td>{{ $dt($e['reception']) }}</td><td>{{ $dt($e['fin']) }}</td><td>{{ $e['duree'] ?? '—' }}</td><td>{{ $e['jours_ouvres'] ?? '—' }}</td>
                        <td>{{ $dt($e['echeance']) }}@if($e['retard']) <span class="badge bg-danger">retard</span>@endif</td>
                    </tr>
                    @if($e['observation'])<tr><td></td><td colspan="10" class="fst-italic text-muted">« {{ $e['observation'] }} »@if($e['categorie_rejet']) ({{ $e['categorie_rejet'] }})@endif</td></tr>@endif
                @endforeach
                </tbody>
            </table></div>
            @endif
        @endforeach
    </div>

    {{-- Historique --}}
    <div class="tab-pane fade @if($onglet === 'historique') show active @endif p-3" id="t-historique">
        <div class="table-responsive"><table class="table table-sm align-middle small mb-0">
            <thead class="table-light"><tr><th>#</th><th>Date</th><th>Dossier</th><th>Étape</th><th>Action</th><th>Acteur</th><th>Fonction / rôle</th><th>Structure</th><th>Statut</th><th>Commentaire</th></tr></thead>
            <tbody>
            @forelse($dossier['historique'] as $h)
                <tr>
                    <td>{{ $h['n'] }}</td><td class="text-nowrap">{{ $dt($h['date']) }}</td><td>{{ $h['processus'] }} <code>{{ $h['dossier'] }}</code></td>
                    <td>{{ $h['etape'] }}</td><td>{{ $h['action'] }}</td><td>{{ $h['acteur'] }}</td>
                    <td>{{ collect([$h['fonction'], $h['role']])->filter()->implode(' · ') ?: '—' }}</td><td>{{ $h['structure'] ?? '—' }}</td>
                    <td>@if($h['statut_avant'] || $h['statut_apres']){{ $h['statut_avant'] ?? '?' }} → {{ $h['statut_apres'] ?? '?' }}@else — @endif</td>
                    <td>{{ $h['commentaire'] ?? '' }} {{ $h['reference'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-muted">Aucun événement.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    {{-- Dossiers liés (arborescence) --}}
    <div class="tab-pane fade @if($onglet === 'chaine') show active @endif p-3" id="t-chaine">
        <div class="table-responsive"><table class="table table-sm align-middle small mb-0">
            <thead class="table-light"><tr><th>Dossier</th><th class="text-end">Montant (XAF)</th><th>Statut</th><th>Créé le</th><th>Action attendue</th><th></th></tr></thead>
            <tbody>
            @foreach($dossier['noeuds'] as $n)
                <tr class="@if($n['objet']->is($dossier['cible'])) table-active @endif">
                    <td style="padding-left: {{ 0.5 + $n['niveau'] * 1.5 }}rem">@if($n['niveau'])<i class="bi bi-arrow-return-right text-muted me-1"></i>@endif<strong>{{ $n['processus'] }}</strong> <code>{{ $n['numero'] }}</code></td>
                    <td class="text-end">{{ $fmt($n['montant']) }}</td><td>{{ $n['statut_libelle'] }}</td><td>{{ $dt($n['date']) }}</td>
                    <td>{{ $n['situation']['attendu']['action'] ?? '—' }}</td>
                    <td class="text-end text-nowrap"><a href="{{ $n['url_tracabilite'] }}" class="btn btn-sm btn-outline-primary">Tracer</a> @if($n['url'])<a href="{{ $n['url'] }}" class="btn btn-sm btn-outline-secondary">Fiche</a>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>

    {{-- Finances --}}
    <div class="tab-pane fade @if($onglet === 'finances') show active @endif p-3" id="t-finances">
        @php $fi = $dossier['finances']; @endphp
        <div class="row g-3 mb-3">
            @foreach(['engage' => 'Engagé', 'liquide' => 'Liquidé', 'reste_a_liquider' => 'Reste à liquider', 'ordonnance' => 'Ordonnancé', 'paye' => 'Payé', 'reste_a_payer' => 'Reste à payer'] as $k => $l)
                <div class="col-6 col-md-2"><div class="text-muted small">{{ $l }}</div><div class="fw-bold">{{ $fmt($fi[$k]) }}</div></div>
            @endforeach
        </div>
        <h6 class="fw-bold">Lignes budgétaires imputées</h6>
        <div class="table-responsive"><table class="table table-sm small mb-0">
            <thead class="table-light"><tr><th>Ligne</th><th>Intitulé</th><th class="text-end">Part du dossier</th><th class="text-end">Crédit</th><th class="text-end">Engagé</th><th class="text-end">Liquidé</th><th class="text-end">Payé</th><th class="text-end">Disponible</th></tr></thead>
            <tbody>
            @forelse($fi['lignes'] as $l)
                <tr><td><code>{{ $l['code'] }}</code></td><td>{{ $l['intitule'] }}</td><td class="text-end">{{ $fmt($l['part_dossier']) }}</td><td class="text-end">{{ $fmt($l['ouvert']) }}</td>
                    <td class="text-end">{{ $fmt($l['engage']) }}</td><td class="text-end">{{ $fmt($l['liquide']) }}</td><td class="text-end">{{ $fmt($l['paye']) }}</td><td class="text-end">{{ $fmt($l['disponible']) }}</td></tr>
            @empty
                <tr><td colspan="8" class="text-muted">Aucune imputation enregistrée (aucun engagement actif).</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    {{-- Documents --}}
    <div class="tab-pane fade @if($onglet === 'documents') show active @endif p-3" id="t-documents">
        <div class="table-responsive"><table class="table table-sm small mb-0">
            <thead class="table-light"><tr><th>Dossier</th><th>Type</th><th>Document</th><th>Déposé par</th><th>Date</th></tr></thead>
            <tbody>
            @forelse($dossier['documents'] as $doc)
                <tr><td>{{ $doc['processus'] }} <code>{{ $doc['dossier'] }}</code></td><td>{{ $doc['type'] }}</td>
                    <td>@if($doc['url'])<a href="{{ $doc['url'] }}">{{ $doc['nom'] }}</a>@else{{ $doc['nom'] }}@endif</td><td>{{ $doc['auteur'] ?? '—' }}</td><td>{{ $dt($doc['date']) }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-muted">Aucune pièce jointe sur la chaîne.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    {{-- Blocages --}}
    <div class="tab-pane fade @if($onglet === 'anomalies') show active @endif p-3" id="t-anomalies">
        @forelse($dossier['anomalies'] as $an)
            <div class="alert alert-{{ $gravites[$an['gravite']] ?? 'secondary' }} py-2 small mb-2">
                <strong>{{ ucfirst($an['gravite']) }}</strong> · {{ $an['processus'] }} <code>{{ $an['dossier'] }}</code> — {{ $an['message'] }}
            </div>
        @empty
            <div class="text-success small"><i class="bi bi-check-circle me-1"></i>Aucun blocage ni incohérence détecté.</div>
        @endforelse
        <div class="small text-muted mt-2">Les anomalies sont signalées, jamais corrigées automatiquement : toute réaffectation ou correction passe par l'administration du workflow.</div>
    </div>
</div>
@endsection
