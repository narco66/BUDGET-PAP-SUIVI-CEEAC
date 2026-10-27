@extends('layouts.app')
@section('titre', 'Tâche '.$tache->reference)
@php
    $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 0, ',', ' ');
    $couleurPriorite = ['faible' => 'secondary', 'normale' => 'info', 'haute' => 'warning', 'urgente' => 'danger'];
    $peut = fn (string $a) => in_array($a, $actions, true);
@endphp

@push('styles')<style>.cd-mt{font-variant-numeric:tabular-nums}.mt-banniere{border-left:6px solid #0a4a8f !important}.mt-chrono{border-left:2px solid #dee2e6}.mt-chrono .pt{width:12px;height:12px;border-radius:50%;background:#0a4a8f;margin-left:-7px;margin-top:5px}</style>@endpush

@section('contenu')
<div class="mb-2"><a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('taches.index') }}" class="small"><i class="bi bi-arrow-left me-1"></i>Retour à la liste</a></div>

{{-- Bannière d'étape persistante (§ 4.3) --}}
<div class="card border-0 shadow-sm mt-banniere mb-3"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <div class="small text-muted">{{ $tache->reference }} · {{ $tache->module }} · {{ $tache->estPreparation() ? 'action hors circuit' : 'circuit '.$tache->workflow_code }}</div>
            <h5 class="fw-bold mb-1">{{ $objet?->numero ?? '#'.$tache->objet_id }} — {{ $tache->objet_libelle }}</h5>
            @if ($tache->estPreparation())
                <div><i class="bi bi-pencil-square me-1"></i><strong>Action attendue :</strong> {{ $tache->libelle_etape }} · acteur compétent : <code>{{ $tache->role }}</code></div>
            @else
                <div><i class="bi bi-signpost-2 me-1"></i><strong>Étape {{ $tache->etape_ordre }} :</strong> {{ $tache->libelle_etape ?? $tache->role }}
                    · action attendue : <strong>{{ $libelleValidation }}</strong> · acteur compétent : <code>{{ $tache->role }}</code></div>
            @endif
        </div>
        <div class="text-end">
            <span class="badge bg-{{ $tache->estOuverte() ? 'primary' : 'success' }} fs-6">{{ \App\Models\WorkflowTache::STATUTS[$tache->statut] }}</span>
            <span class="badge bg-{{ $couleurPriorite[$priorite] ?? 'secondary' }} fs-6">{{ \App\Models\WorkflowTache::PRIORITES[$priorite] }}</span>
            @if ($tache->decision)<div class="mt-1 fw-semibold">{{ \App\Models\WorkflowTache::DECISIONS[$tache->decision] ?? $tache->decision }}</div>@endif
        </div>
    </div>
    <div class="row small mt-2 g-2">
        <div class="col-md-3"><span class="text-muted">Reçue le</span><br><span class="cd-mt">{{ \App\Support\Horodatage::local($tache->created_at, 'd/m/Y H:i') }}</span></div>
        <div class="col-md-3"><span class="text-muted">Échéance</span><br><span class="cd-mt {{ $tache->enRetard() ? 'text-danger fw-bold' : '' }}">{{ \App\Support\Horodatage::local($tache->echeance_le, 'd/m/Y H:i') }}{{ $tache->enRetard() ? ' — en retard' : '' }}</span></div>
        <div class="col-md-3"><span class="text-muted">Affectataire</span><br>{{ $tache->assigne?->name ?? 'Non prise en charge' }}</div>
        <div class="col-md-3"><span class="text-muted">Unité initiatrice</span><br>{{ $tache->structure ? $tache->structure->code.' — '.$tache->structure->libelle : '—' }}</div>
    </div>
    @if ($tache->statut === 'suspendue')<div class="alert alert-warning py-1 small mt-2 mb-0"><i class="bi bi-pause-circle me-1"></i>Suspendue le {{ \App\Support\Horodatage::local($tache->suspendue_le, 'd/m/Y H:i') }} : {{ $tache->motif_suspension }}</div>@endif
    @if ($tache->estOuverte() && ! $eligibilite['ok'])<div class="alert alert-light border py-1 small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Consultation seule : {{ $eligibilite['motif'] }}</div>@endif
    @if ($tache->estOuverte() && $tache->affectation_defaut)<div class="alert alert-warning py-1 small mt-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Aucun titulaire organique n'est paramétré pour l'unité concernée : la tâche est ouverte à tous les porteurs du rôle <code>{{ $tache->role }}</code> (anomalie d'affectation signalée en supervision).</div>@endif
    @if ($tache->estPreparation() && $tache->estOuverte())<div class="alert alert-light border py-1 small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Cette action se réalise dans le dossier ; la tâche se clôt d'elle-même dès que le dossier avance.</div>@endif
    @if ($eligibilite['delegue'])<div class="alert alert-info py-1 small mt-2 mb-0"><i class="bi bi-person-badge me-1"></i>Vous traitez cette tâche par délégation / intérim : la décision sera tracée à votre nom pour le compte du titulaire.</div>@endif
</div></div>

<div class="row g-3">
    <div class="col-lg-8">
        {{-- Informations métier et financières issues du dossier source --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span><i class="bi bi-file-earmark-text me-1"></i>Dossier</span>
                @if ($urlDossier)<a href="{{ $urlDossier }}" class="small fw-normal">Ouvrir le dossier complet →</a>@endif</div>
            <div class="card-body small">
                <div class="row g-2">
                    <div class="col-md-4"><span class="text-muted">Référence</span><br>{{ $objet?->numero ?? '—' }}</div>
                    <div class="col-md-4"><span class="text-muted">Statut du dossier</span><br>{{ $objet?->statut ?? '—' }}</div>
                    <div class="col-md-4"><span class="text-muted">Montant</span><br><span class="cd-mt fw-semibold">{{ $fmt($montant) }}</span> XAF</div>
                    @if ($objet?->exercice_id)<div class="col-md-4"><span class="text-muted">Exercice</span><br>{{ $objet->exercice?->annee ?? $objet->exercice_id }}</div>@endif
                    <div class="col-md-8"><span class="text-muted">Objet</span><br>{{ $objet?->objet ?? $tache->objet_libelle }}</div>
                </div>
                @if (! empty($finances['ligne']))
                    @php $l = $finances['ligne']; @endphp
                    <hr><div class="fw-semibold mb-1">Imputation budgétaire — {{ $l['code'] }} {{ $l['intitule'] ? '· '.$l['intitule'] : '' }}</div>
                    <div class="row text-center g-2">
                        <div class="col"><div class="cd-mt fw-bold">{{ $fmt($l['initial']) }}</div><div class="text-muted">Crédit initial</div></div>
                        <div class="col"><div class="cd-mt fw-bold">{{ $fmt($l['revise']) }}</div><div class="text-muted">Crédit révisé</div></div>
                        <div class="col"><div class="cd-mt fw-bold text-primary">{{ $fmt($l['engage']) }}</div><div class="text-muted">Engagé</div></div>
                        <div class="col"><div class="cd-mt fw-bold {{ $l['disponible'] < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($l['disponible']) }}</div><div class="text-muted">Disponible</div></div>
                    </div>
                @endif
                @if (! empty($finances['paiement']))
                    @php $p = $finances['paiement']; @endphp
                    <hr><div class="row text-center g-2">
                        <div class="col"><div class="cd-mt fw-bold">{{ $fmt($p['ordonnance']) }}</div><div class="text-muted">Ordonnancé</div></div>
                        <div class="col"><div class="cd-mt fw-bold text-success">{{ $fmt($p['paye']) }}</div><div class="text-muted">Déjà payé</div></div>
                        <div class="col"><div class="cd-mt fw-bold text-danger">{{ $fmt($p['reste']) }}</div><div class="text-muted">Reste à payer</div></div>
                    </div>
                @endif
                @if (! empty($finances['engagement']) && ! $objet instanceof \App\Models\Engagement)
                    <div class="mt-2 text-muted">Engagement d'origine : {{ $finances['engagement']['numero'] }} ({{ $fmt($finances['engagement']['montant']) }} XAF, {{ $finances['engagement']['statut'] }})</div>
                @endif
            </div>
        </div>

        {{-- Pièces justificatives et GED --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-paperclip me-1"></i>Pièces justificatives ({{ $pieces->count() + $documentsGed->count() }})</div>
            <ul class="list-group list-group-flush small">
                @foreach ($pieces as $piece)
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-file-earmark me-1"></i>{{ $piece->nom }} <span class="text-muted">{{ $piece->categorie }}</span></span>
                        <a href="{{ route('pieces.download', $piece) }}">Télécharger</a></li>
                @endforeach
                @foreach ($documentsGed as $doc)
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-archive me-1"></i>{{ $doc->reference }} — {{ $doc->titre }} <span class="text-muted">v{{ $doc->version_courante }} · {{ $doc->statut }}</span></span>
                        <a href="{{ route('ged.documents.show', $doc) }}">Consulter</a></li>
                @endforeach
                @if ($pieces->isEmpty() && $documentsGed->isEmpty())<li class="list-group-item text-muted">Aucune pièce jointe au dossier.</li>@endif
            </ul>
        </div>

        {{-- Chronologie complète du circuit --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i>Chronologie</div>
            <div class="card-body">
                @forelse ($evenements as $e)
                    <div class="d-flex mt-chrono ps-3 pb-3">
                        <div class="pt flex-shrink-0 me-2"></div>
                        <div class="small">
                            <div><strong>{{ \App\Models\WorkflowTacheEvenement::TYPES[$e->type] ?? $e->type }}</strong>
                                @if ($e->type === 'decision')— {{ \App\Models\WorkflowTache::DECISIONS[$e->donnees['decision'] ?? ''] ?? '' }}@endif
                                <span class="text-muted">· {{ $tachesInstance[$e->workflow_tache_id] ?? '' }}</span></div>
                            <div class="text-muted">{{ \App\Support\Horodatage::local($e->created_at, 'd/m/Y H:i') }} · {{ $e->user?->name ?? 'Système' }}@if($e->pourCompteDe) pour le compte de {{ $e->pourCompteDe->name }}@endif</div>
                            @if ($e->commentaire)<div class="mt-1">« {{ $e->commentaire }} »</div>@endif
                        </div>
                    </div>
                @empty
                    <div class="text-muted small">Aucun événement.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Actions calculées côté serveur --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-lightning me-1"></i>Actions</div>
            <div class="card-body d-grid gap-2">
                @if ($peut('prendre_en_charge'))
                    <form method="POST" action="{{ route('taches.agir', [$tache, 'prendre_en_charge']) }}">@csrf<input type="hidden" name="version" value="{{ $tache->version }}">
                        <button class="btn btn-outline-success w-100"><i class="bi bi-hand-index me-1"></i>Prendre en charge</button></form>
                @endif
                @if ($peut('traiter_dossier') && $urlDossier)
                    <a href="{{ $urlDossier }}" class="btn btn-success"><i class="bi bi-pencil-square me-1"></i>Traiter dans le dossier</a>
                @endif
                @if ($peut('valider'))
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-decision" data-decision="valider"><i class="bi bi-check2-circle me-1"></i>{{ $libelleValidation }}</button>
                    @if ($peut('renvoyer'))<button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modal-decision" data-decision="renvoyer"><i class="bi bi-arrow-return-left me-1"></i>Retourner pour correction</button>@endif
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-decision" data-decision="rejeter"><i class="bi bi-x-circle me-1"></i>Rejeter</button>
                @endif
                @if ($peut('liberer'))
                    <form method="POST" action="{{ route('taches.agir', [$tache, 'liberer']) }}">@csrf<input type="hidden" name="version" value="{{ $tache->version }}">
                        <button class="btn btn-outline-secondary w-100 btn-sm"><i class="bi bi-box-arrow-up me-1"></i>Libérer la tâche</button></form>
                @endif
                @if ($peut('reprendre'))
                    <form method="POST" action="{{ route('taches.agir', [$tache, 'reprendre']) }}">@csrf<input type="hidden" name="version" value="{{ $tache->version }}">
                        <button class="btn btn-outline-primary w-100"><i class="bi bi-play-circle me-1"></i>Reprendre</button></form>
                @endif
                @if ($peut('suspendre'))
                    <form method="POST" action="{{ route('taches.agir', [$tache, 'suspendre']) }}" class="input-group input-group-sm">@csrf<input type="hidden" name="version" value="{{ $tache->version }}">
                        <input name="motif" class="form-control" placeholder="Motif de suspension" required><button class="btn btn-outline-warning"><i class="bi bi-pause"></i></button></form>
                @endif
                @if ($urlDossier && ! $peut('traiter_dossier'))<a href="{{ $urlDossier }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder2-open me-1"></i>Consulter le dossier</a>@endif
                @if ($objet && $typeTrace = \App\Services\Depense\TracabiliteDepenseService::typeDe($objet))<a href="{{ route('depense.tracabilite.show', [$typeTrace, $objet->getKey()]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-diagram-3 me-1"></i>Traçabilité du dossier</a>@endif
                @if (count($actions) === 1)<div class="text-muted small">Aucune action disponible pour vous sur cette tâche.</div>@endif
            </div>
        </div>

        @if ($peut('reaffecter') || $peut('prioriser'))
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-2 me-1"></i>Supervision</div>
            <div class="card-body small">
                @if ($peut('reaffecter'))
                <form method="POST" action="{{ route('taches.agir', [$tache, 'reaffecter']) }}" class="mb-3">@csrf<input type="hidden" name="version" value="{{ $tache->version }}">
                    <label class="form-label mb-0">Réaffecter à</label>
                    <select name="user_id" class="form-select form-select-sm mb-1" required>
                        <option value="">— agent habilité —</option>
                        @foreach ($candidats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    <input name="motif" class="form-control form-control-sm mb-1" placeholder="Motif (obligatoire)" required>
                    <button class="btn btn-sm btn-outline-dark w-100">Réaffecter</button>
                </form>
                @endif
                @if ($peut('prioriser'))
                <form method="POST" action="{{ route('taches.agir', [$tache, 'prioriser']) }}">@csrf<input type="hidden" name="version" value="{{ $tache->version }}">
                    <label class="form-label mb-0">Priorité</label>
                    <select name="priorite" class="form-select form-select-sm mb-1">@foreach (\App\Models\WorkflowTache::PRIORITES as $c => $l)<option value="{{ $c }}" @selected($tache->priorite === $c)>{{ $l }}</option>@endforeach</select>
                    <input name="motif" class="form-control form-control-sm mb-1" placeholder="Motif (obligatoire)" required>
                    <button class="btn btn-sm btn-outline-dark w-100">Modifier la priorité</button>
                </form>
                @endif
            </div>
        </div>
        @endif

        @if ($peut('commenter'))
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-left-text me-1"></i>Observation</div>
            <form method="POST" action="{{ route('taches.agir', [$tache, 'commenter']) }}" class="card-body">@csrf
                <textarea name="commentaire" class="form-control form-control-sm mb-2" rows="3" maxlength="2000" required placeholder="Observation de travail, réserve…"></textarea>
                <button class="btn btn-sm btn-outline-primary w-100">Enregistrer l'observation</button>
            </form>
        </div>
        @endif
    </div>
</div>

@if ($peut('valider'))
{{-- Confirmation de décision (§ 8) --}}
<div class="modal fade" id="modal-decision" tabindex="-1" aria-labelledby="titre-decision" aria-hidden="true">
    <div class="modal-dialog"><form method="POST" action="{{ route('taches.agir', [$tache, 'decider']) }}" class="modal-content">@csrf
        <input type="hidden" name="version" value="{{ $tache->version }}">
        <input type="hidden" name="decision" id="champ-decision" value="valider">
        <div class="modal-header"><h5 class="modal-title" id="titre-decision">Confirmer la décision</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body small">
            <p class="mb-2">Dossier <strong>{{ $objet?->numero }}</strong> — {{ $tache->objet_libelle }}<br>Étape {{ $tache->etape_ordre }} : {{ $tache->libelle_etape }}</p>
            <p class="mb-2" id="consequence-decision"></p>
            <div id="bloc-categorie" class="mb-2 d-none"><label class="form-label mb-0">Catégorie du rejet</label>
                <select name="categorie_rejet" class="form-select form-select-sm">@foreach (\App\Support\WorkflowRejetCategorie::values() as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></div>
            <label class="form-label mb-0">Observation <span id="obs-obligatoire" class="text-danger d-none">(obligatoire)</span></label>
            <textarea name="observation" id="champ-observation" class="form-control form-control-sm" rows="3" maxlength="1000"></textarea>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-sm btn-primary">Confirmer</button></div>
    </form></div>
</div>
@push('scripts')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.getElementById('modal-decision')?.addEventListener('show.bs.modal', (e) => {
    const d = e.relatedTarget?.dataset.decision || 'valider';
    const textes = {
        valider: @json($libelleValidation.' : l\'étape est clôturée et le dossier passe à l\'étape suivante du circuit.'),
        renvoyer: 'Retour pour correction : le dossier revient à l\'étape de correction prévue ; une nouvelle tâche sera créée.',
        rejeter: 'Rejet : le circuit est clôturé, le dossier est rejeté. Motif et catégorie obligatoires.',
    };
    document.getElementById('champ-decision').value = d;
    document.getElementById('consequence-decision').textContent = textes[d];
    document.getElementById('bloc-categorie').classList.toggle('d-none', d !== 'rejeter');
    document.getElementById('obs-obligatoire').classList.toggle('d-none', d === 'valider');
    document.getElementById('champ-observation').required = d !== 'valider';
});
</script>
@endpush
@endif
@endsection
