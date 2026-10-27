@extends('layouts.app')
@section('titre', 'Import '.$lot->reference)

@php
    $couleurs = ['brouillon' => 'secondary', 'fichier_charge' => 'info', 'mapping' => 'warning', 'anomalies_detectees' => 'danger', 'simule' => 'primary',
        'en_attente_approbation' => 'warning', 'approuve' => 'info', 'en_cours' => 'info', 'termine' => 'success', 'echec' => 'danger', 'rejete' => 'danger', 'annule' => 'dark'];
    $couleursOp = ['inserer' => 'success', 'mettre_a_jour' => 'primary', 'identique' => 'secondary', 'ignorer' => 'light text-dark border', 'conflit' => 'warning text-dark', 'erreur' => 'danger'];
    $couleursGr = ['bloquante' => 'danger', 'majeure' => 'warning text-dark', 'avertissement' => 'info text-dark'];
    $sensible = $domaine->circuit() !== null;
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $date = fn ($d) => $d ? \App\Support\Horodatage::local($d, 'd/m/Y H:i') : '—';
    $prochaine = match ($lot->statut) {
        'mapping' => 'Associez les colonnes obligatoires manquantes dans l\'onglet « Correspondance ».',
        'anomalies_detectees' => 'Corrigez le fichier (voir « Anomalies ») puis déposez-le de nouveau : aucune donnée n\'a été écrite.',
        'simule' => $lot->mode === 'simulation' ? 'Simulation terminée : aucune intégration possible dans ce mode.'
            : ($lot->total('a_ecrire') === 0 ? 'Aucune ligne à intégrer : tout est identique, ignoré ou en conflit.'
            : ($sensible ? 'Vérifiez la prévisualisation puis soumettez le lot à approbation.' : 'Vérifiez la prévisualisation puis exécutez le lot.')),
        'en_attente_approbation' => 'En attente de l\'approbation d\'un agent habilité (tâche dans « Mes Tâches »).',
        'approuve' => 'Lot approuvé : il peut être exécuté.',
        'en_cours' => 'Exécution en cours (avancement '.$lot->progression.' %).',
        'termine' => 'Import terminé et vérifié.',
        'echec' => 'Échec : aucune donnée n\'a été intégrée. '.$lot->motif,
        'rejete' => 'Lot rejeté par l\'approbateur. '.$lot->motif,
        'annule' => 'Lot annulé. '.$lot->motif,
        default => null,
    };
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
    <div>
        <div class="small text-muted"><a href="{{ route('imports.index') }}">Import de données</a> / {{ $lot->reference }}</div>
        <h1 class="h4 mb-1">{{ $domaine->libelle() }} <span class="badge bg-{{ $couleurs[$lot->statut] ?? 'secondary' }} fs-6 align-middle">{{ $lot->libelleStatut() }}</span></h1>
        <div class="small text-muted">
            {{ \App\Models\ImportLot::MODES[$lot->mode] ?? $lot->mode }}@if ($lot->exercice) · exercice {{ $lot->exercice->annee }}@endif
            · créé par {{ $lot->createur?->name ?? '—' }} le {{ $date($lot->created_at) }}
            @if ($lot->fichier_nom) · <i class="bi bi-file-earmark me-1"></i>{{ $lot->fichier_nom }} ({{ $fmt(($lot->fichier_taille ?? 0) / 1024) }} Ko, empreinte <code title="{{ $lot->fichier_empreinte }}">{{ substr((string) $lot->fichier_empreinte, 0, 12) }}…</code>)@endif
        </div>
    </div>
    @if ($peutRapport && $lot->analyse_le)
    <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Rapports</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('imports.rapport', [$lot, 'anomalies', 'xlsx']) }}">Anomalies (Excel)</a></li>
            <li><a class="dropdown-item" href="{{ route('imports.rapport', [$lot, 'anomalies', 'csv']) }}">Anomalies (CSV)</a></li>
            <li><a class="dropdown-item" href="{{ route('imports.rapport', [$lot, 'operations', 'xlsx']) }}">Plan d'opérations (Excel)</a></li>
            <li><a class="dropdown-item" href="{{ route('imports.rapport', [$lot, 'operations', 'csv']) }}">Plan d'opérations (CSV)</a></li>
        </ul>
    </div>
    @endif
</div>

@if ($prochaine)
<div class="alert alert-{{ in_array($lot->statut, ['echec', 'rejete', 'anomalies_detectees'], true) ? 'danger' : ($lot->statut === 'termine' ? 'success' : 'primary') }} py-2"><i class="bi bi-signpost-2 me-1"></i>{{ $prochaine }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-9">
        <ul class="nav nav-tabs">
            @foreach (['synthese' => 'Synthèse', 'correspondance' => 'Correspondance', 'apercu' => 'Prévisualisation', 'anomalies' => 'Anomalies ('.($lot->total('bloquantes') + $lot->total('majeures') + $lot->total('avertissements')).')', 'historique' => 'Historique'] as $cle => $libelle)
                <li class="nav-item"><a class="nav-link {{ $onglet === $cle ? 'active fw-semibold' : '' }}" href="{{ route('imports.show', [$lot, 'onglet' => $cle]) }}">{{ $libelle }}</a></li>
            @endforeach
        </ul>
        <div class="card border-0 shadow-sm border-top-0 rounded-top-0"><div class="card-body">

        @if ($onglet === 'synthese')
            @if (! $lot->analyse_le)
                <p class="text-muted mb-0">Le lot n'a pas encore été analysé.</p>
            @else
            <div class="row g-2 mb-3">
                @foreach ([['inserer', 'Insertions'], ['mettre_a_jour', 'Mises à jour'], ['identique', 'Identiques'], ['ignorer', 'Ignorées'], ['conflit', 'Conflits'], ['erreur', 'En erreur']] as [$op, $libelle])
                    <div class="col-4 col-md-2"><a href="{{ route('imports.show', [$lot, 'onglet' => 'apercu', 'operation' => $op]) }}" class="card border text-decoration-none h-100"><div class="card-body py-2 text-center">
                        <div class="fs-5 fw-bold">{{ $lot->total($op) }}</div><span class="badge bg-{{ $couleursOp[$op] }}">{{ $libelle }}</span>
                    </div></a></div>
                @endforeach
            </div>
            <table class="table table-sm small mb-0">
                <tr><th class="w-50">Lignes lues</th><td>{{ $lot->total('lignes') }}</td></tr>
                <tr><th>Lignes à intégrer</th><td><strong>{{ $lot->total('a_ecrire') }}</strong>@if ($lot->total('montant')) · montant {{ $fmt($lot->total('montant')) }} XAF @endif</td></tr>
                @if ($lot->statut === 'termine')<tr><th>Lignes intégrées (vérifiées)</th><td class="text-success fw-semibold">{{ $lot->total('executees') }}</td></tr>@endif
                <tr><th>Anomalies</th><td><span class="badge bg-danger">{{ $lot->total('bloquantes') }} bloquante(s)</span> <span class="badge bg-warning text-dark">{{ $lot->total('majeures') }} majeure(s)</span> <span class="badge bg-info text-dark">{{ $lot->total('avertissements') }} avertissement(s)</span></td></tr>
                <tr><th>Analysé le</th><td>{{ $date($lot->analyse_le) }}</td></tr>
                @if ($lot->approuve_le)<tr><th>Approuvé</th><td>{{ $lot->approbateur?->name }} le {{ $date($lot->approuve_le) }}</td></tr>@endif
                @if ($lot->execute_le)<tr><th>Exécuté</th><td>{{ $lot->executant?->name }} le {{ $date($lot->execute_le) }}</td></tr>@endif
            </table>
            @if ($lot->total('conflit') > 0)
                <div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>{{ $lot->total('conflit') }} ligne(s) en conflit avec des données protégées (crédit ouvert, rattachement, mesure validée…) : elles ne seront <strong>pas</strong> intégrées. Elles relèvent de la procédure métier dédiée.</div>
            @endif
            @endif

        @elseif ($onglet === 'correspondance')
            @if ($manquants)<div class="alert alert-warning small">Colonnes obligatoires non associées : <strong>{{ implode(', ', $manquants) }}</strong>.</div>@endif
            <form method="POST" action="{{ route('imports.mapping', $lot) }}">@csrf @method('PUT')
                <table class="table table-sm align-middle small">
                    <thead class="table-light"><tr><th>Champ métier</th><th>Type</th><th>Colonne du fichier</th></tr></thead>
                    <tbody>
                    @foreach ($domaine->champs() as $c)
                        <tr>
                            <td><code>{{ $c->champ }}</code> @if ($c->requis)<span class="text-danger" title="Obligatoire">*</span>@endif<div class="text-muted">{{ $c->libelle }}@if ($c->aide) — {{ $c->aide }}@endif</div></td>
                            <td>{{ $c->type }}</td>
                            <td><select name="mapping[{{ $c->champ }}]" class="form-select form-select-sm" @disabled(! $peutPreparer)>
                                <option value="">— non importé —</option>
                                @foreach ($lot->entetes ?? [] as $entete)<option value="{{ $entete }}" @selected(($lot->mapping[$c->champ] ?? null) === $entete)>{{ $entete }}</option>@endforeach
                            </select></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if ($colonnesIgnorees)<p class="small text-muted">Colonnes du fichier non importées : {{ implode(', ', $colonnesIgnorees) }}.</p>@endif
                @if ($peutPreparer)
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" name="nom_modele" class="form-control form-control-sm" style="max-width:18rem" placeholder="Enregistrer comme modèle (nom, facultatif)">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Appliquer et réanalyser</button>
                </div>
                @endif
            </form>

        @elseif ($onglet === 'apercu')
            <div class="mb-2 small">Filtrer :
                <a href="{{ route('imports.show', [$lot, 'onglet' => 'apercu']) }}" class="badge bg-secondary text-decoration-none">Toutes</a>
                @foreach (\App\Models\ImportLigne::OPERATIONS as $op => $libelle)<a href="{{ route('imports.show', [$lot, 'onglet' => 'apercu', 'operation' => $op]) }}" class="badge bg-{{ $couleursOp[$op] }} text-decoration-none">{{ $libelle }}</a> @endforeach
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle small">
                <thead class="table-light"><tr><th>Ligne</th><th>Opération</th>@foreach ($domaine->champs() as $c)<th>{{ $c->champ }}</th>@endforeach<th>Différences avec la base</th></tr></thead>
                <tbody>
                @forelse ($lignes as $l)
                    <tr>
                        <td>{{ $l->numero }}</td>
                        <td><span class="badge bg-{{ $couleursOp[$l->operation] ?? 'secondary' }}">{{ \App\Models\ImportLigne::OPERATIONS[$l->operation] ?? $l->operation }}</span>@if ($l->statut === 'execute')<div class="text-success">intégrée</div>@endif</td>
                        @foreach ($domaine->champs() as $c)<td class="text-truncate" style="max-width:12rem" title="{{ is_scalar($l->donnees[$c->champ] ?? null) ? $l->donnees[$c->champ] : '' }}">{{ is_bool($l->donnees[$c->champ] ?? null) ? ($l->donnees[$c->champ] ? 'oui' : 'non') : ($l->donnees[$c->champ] ?? '') }}</td>@endforeach
                        <td>@foreach ($l->differences ?? [] as $champ => $d)<div><code>{{ $champ }}</code> : <span class="text-danger text-decoration-line-through">{{ is_bool($d['avant']) ? ($d['avant'] ? 'oui' : 'non') : $d['avant'] }}</span> → <span class="text-success">{{ is_bool($d['apres']) ? ($d['apres'] ? 'oui' : 'non') : $d['apres'] }}</span></div>@endforeach</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($domaine->champs()) + 3 }}" class="text-muted text-center">Aucune ligne.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $lignes->links() }}

        @elseif ($onglet === 'anomalies')
            <div class="mb-2 small">Filtrer :
                <a href="{{ route('imports.show', [$lot, 'onglet' => 'anomalies']) }}" class="badge bg-secondary text-decoration-none">Toutes</a>
                @foreach (\App\Models\ImportAnomalie::GRAVITES as $g => $libelle)<a href="{{ route('imports.show', [$lot, 'onglet' => 'anomalies', 'gravite' => $g]) }}" class="badge bg-{{ $couleursGr[$g] }} text-decoration-none">{{ $libelle }}</a> @endforeach
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle small">
                <thead class="table-light"><tr><th>Ligne</th><th>Gravité</th><th>Colonne</th><th>Valeur</th><th>Règle</th><th>Message</th><th>Correction suggérée</th></tr></thead>
                <tbody>
                @forelse ($anomalies as $a)
                    <tr>
                        <td>{{ $a->numero_ligne ?? 'lot' }}</td>
                        <td><span class="badge bg-{{ $couleursGr[$a->gravite] ?? 'secondary' }}">{{ \App\Models\ImportAnomalie::GRAVITES[$a->gravite] ?? $a->gravite }}</span></td>
                        <td><code>{{ $a->colonne }}</code></td>
                        <td class="text-truncate" style="max-width:10rem" title="{{ $a->valeur }}">{{ $a->valeur }}</td>
                        <td><code>{{ $a->regle }}</code></td>
                        <td>{{ $a->message }}</td>
                        <td class="text-muted">{{ $a->suggestion }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted text-center">Aucune anomalie.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $anomalies->links() }}

        @else
            <ul class="list-unstyled small mb-0">
            @foreach ($lot->evenements as $e)
                <li class="mb-2 pb-2 border-bottom">
                    <strong>{{ str_replace('_', ' ', ucfirst($e->type)) }}</strong>
                    @if ($e->statut_apres && $e->statut_avant !== $e->statut_apres)<span class="text-muted">({{ \App\Models\ImportLot::STATUTS[$e->statut_avant] ?? '—' }} → {{ \App\Models\ImportLot::STATUTS[$e->statut_apres] ?? $e->statut_apres }})</span>@endif
                    <div class="text-muted">{{ $date($e->created_at) }} · {{ $e->user?->name ?? 'Système' }}</div>
                    @if ($e->commentaire)<div>« {{ $e->commentaire }} »</div>@endif
                </li>
            @endforeach
            </ul>
        @endif
        </div></div>
    </div>

    <div class="col-lg-3">
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold small"><i class="bi bi-lightning me-1"></i>Actions</div>
        <div class="card-body d-grid gap-2 small">
            @if ($peutPreparer)
                <form method="POST" action="{{ route('imports.deposer', $lot) }}" enctype="multipart/form-data" class="d-grid gap-1">@csrf
                    <input type="file" name="fichier" class="form-control form-control-sm" required accept=".xlsx,.xls,.csv,.json">
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i>Déposer un fichier corrigé</button>
                </form>
                @if ($lot->analyse_le)
                <form method="POST" action="{{ route('imports.analyser', $lot) }}">@csrf<button class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-arrow-repeat me-1"></i>Réanalyser sur la base actuelle</button></form>
                @endif
            @endif
            @if ($peutSoumettre)
                <form method="POST" action="{{ route('imports.soumettre', $lot) }}" class="d-grid gap-1">@csrf
                    @if ($lot->total('conflit') > 0 || $lot->total('majeures') > 0)
                        <label class="form-check"><input type="checkbox" name="conflits_acceptes" value="1" class="form-check-input" required> <span class="form-check-label">J'ai pris connaissance des {{ $lot->total('conflit') }} conflit(s) non intégrés.</span></label>
                    @endif
                    <button class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Soumettre à approbation</button>
                </form>
            @endif
            @if ($peutExecuter)
                <form method="POST" action="{{ route('imports.executer', $lot) }}" class="d-grid gap-1">@csrf
                    <label class="form-check"><input type="checkbox" name="confirmation" value="1" class="form-check-input" required> <span class="form-check-label">Je confirme l'intégration définitive de {{ $lot->total('a_ecrire') }} ligne(s).</span></label>
                    <button class="btn btn-sm btn-success"><i class="bi bi-play-circle me-1"></i>Exécuter l'import</button>
                </form>
            @endif
            @if ($peutAnnuler)
                <form method="POST" action="{{ route('imports.annuler', $lot) }}" class="d-grid gap-1">@csrf
                    <input type="text" name="motif" class="form-control form-control-sm" placeholder="Motif d'annulation" required>
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Annuler le lot</button>
                </form>
            @endif
            @if (! $peutPreparer && ! $peutSoumettre && ! $peutExecuter && ! $peutAnnuler && ! $peutAgir)
                <span class="text-muted">Aucune action disponible pour vous sur ce lot.</span>
            @endif
        </div></div>

        @if ($peutAgir)
            @include('partials._workflow_agir', ['action' => route('imports.agir', $lot), 'avecRenvoi' => false])
        @endif
        @if ($instance)
            <div class="mt-3">@include('partials._timeline', ['instance' => $instance, 'etC' => ['en_attente' => 'secondary', 'valide' => 'success', 'rejete' => 'danger', 'renvoye' => 'warning'], 'objet' => $lot])</div>
        @endif
    </div>
</div>
@endsection
