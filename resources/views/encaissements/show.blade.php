@extends('layouts.app')
@section('titre', 'Encaissement '.$encaissement->reference)

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $badge = ['saisi'=>'bg-warning text-dark','en_validation'=>'bg-info text-dark','rejete'=>'bg-danger','valide'=>'bg-success','rapproche'=>'bg-primary','annule'=>'bg-secondary'][$encaissement->statut] ?? 'bg-light text-dark';
@endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('encaissements.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    <div class="d-flex gap-2">
        @if (in_array($encaissement->statut, ['saisi', 'rejete'], true))
            @can('soumettre', $encaissement)
            <form method="POST" action="{{ route('encaissements.soumettre', $encaissement) }}" onsubmit="return confirm('Soumettre au circuit de validation recettes ?')">
                @csrf
                <button class="btn btn-sm btn-primary" @disabled($blocages !== []) @if($blocages !== []) title="Prérequis manquants : voir ci-dessous" @endif><i class="bi bi-send me-1"></i>Soumettre</button>
            </form>
            @endcan
            @can('annuler', $encaissement)
            <form method="POST" action="{{ route('encaissements.annuler', $encaissement) }}" onsubmit="return confirm('Annuler ?')">
                @csrf <button class="btn btn-sm btn-outline-secondary">Annuler</button>
            </form>
            @endcan
        @elseif ($encaissement->statut === 'valide')
            @can('rapprocher', $encaissement)
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#rappModal"><i class="bi bi-bank me-1"></i>Rapprocher</button>
            @endcan
        @endif
        @can('delete', $encaissement)
            <form method="POST" action="{{ route('encaissements.destroy', $encaissement) }}" onsubmit="return confirm('Supprimer ?')">
                @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        @endcan
    </div>
</div>

@if ($blocages !== [])
<div class="alert alert-warning d-flex gap-2" role="alert">
    <i class="bi bi-exclamation-triangle fs-5"></i>
    <div>
        <strong>Soumission au circuit de validation impossible pour l'instant.</strong>
        <ul class="mb-0 mt-1 small">
            @isset($blocages['rubrique'])
            <li>
                La recette <strong>{{ $encaissement->recette?->reference }}</strong> n'est pas ventilée (rubrique budgétaire manquante).
                @if ($encaissement->recette)
                @can('view', $encaissement->recette)
                <a href="{{ route('recettes.show', $encaissement->recette) }}#ventilation" class="alert-link">Ventiler la recette</a>
                @endcan
                @endif
            </li>
            @endisset
            @isset($blocages['justificatif'])
            <li>Aucun justificatif n'est rattaché. <a href="#justificatifs" class="alert-link">Ajouter un justificatif</a></li>
            @endisset
        </ul>
    </div>
</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h5 class="mb-0"><code>{{ $encaissement->reference }}</code></h5>
                <span class="badge {{ $badge }}">{{ \App\Models\Encaissement::STATUTS[$encaissement->statut] ?? $encaissement->statut }}</span>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted">Recette</dt><dd class="col-sm-8"><a href="{{ route('recettes.show', $encaissement->recette) }}">{{ $encaissement->recette?->reference }}</a></dd>
                <dt class="col-sm-4 text-muted">Catégorie</dt><dd class="col-sm-8">{{ $encaissement->recette?->categorie?->libelle ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Mode de paiement</dt><dd class="col-sm-8">{{ \App\Models\Encaissement::MODES_PAIEMENT[$encaissement->mode_paiement] ?? $encaissement->mode_paiement }}</dd>
                <dt class="col-sm-4 text-muted">Banque</dt><dd class="col-sm-8">{{ $encaissement->banque ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Référence bancaire</dt><dd class="col-sm-8">{{ $encaissement->reference_bancaire ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Date d'encaissement</dt><dd class="col-sm-8">{{ $encaissement->date_encaissement?->format('d/m/Y') }}</dd>
                <dt class="col-sm-4 text-muted">Saisi par</dt><dd class="col-sm-8">{{ $encaissement->saisiPar?->name ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Validé par</dt><dd class="col-sm-8">{{ $encaissement->validePar?->name ?? '—' }}{{ $encaissement->valide_le ? ' · '.$encaissement->valide_le->format('d/m/Y') : '' }}</dd>
                @if ($encaissement->rapproche)
                <dt class="col-sm-4 text-muted">Rapproché</dt><dd class="col-sm-8">{{ $encaissement->date_rapprochement?->format('d/m/Y') }} · {{ $encaissement->rapprochePar?->name }}</dd>
                @endif
                <dt class="col-sm-4 text-muted">Observations</dt><dd class="col-sm-8">{{ $encaissement->observations ?? '—' }}</dd>
            </dl>
        </div></div>
        <div class="card border-0 shadow-sm" id="justificatifs">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-folder2-open me-1"></i>Justificatifs (GED) ({{ $encaissement->gedDocuments->count() }})</span>
                @can('ged.voir')
                <a href="{{ route('ged.documents.index', ['module_source' => 'ENCAISS']) }}" class="btn btn-sm btn-outline-primary">Voir dans la GED</a>
                @endcan
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($encaissement->gedDocuments as $doc)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="small">
                            <i class="bi bi-file-earmark-text me-1"></i>
                            @can('ged.voir')<a href="{{ route('ged.documents.show', $doc) }}" class="fw-semibold">{{ $doc->titre }}</a>@else<span class="fw-semibold">{{ $doc->titre }}</span>@endcan
                            <div class="text-muted font-monospace">{{ $doc->reference }}</div>
                            <span class="text-muted">{{ $doc->createur?->name }} · {{ $doc->date_depot?->format('d/m/Y') }}</span>
                        </div>
                        @can('ged.voir')<a href="{{ route('ged.documents.download', $doc) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>@endcan
                    </li>
                @empty
                    <li class="list-group-item text-muted small">Aucun justificatif rattaché.</li>
                @endforelse
            </ul>
            @can('create', App\Models\Encaissement::class)
            <div class="card-body border-top">
                <form action="{{ route('encaissements.justificatifs.store', $encaissement) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small mb-0">Ajouter un justificatif</label>
                        <input type="file" name="fichier" class="form-control form-control-sm" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt,.csv">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-0">Titre (optionnel)</label>
                        <input type="text" name="titre" class="form-control form-control-sm" placeholder="Titre du document">
                    </div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-cloud-upload"></i></button></div>
                    <div class="col-12"><div class="form-text">Dépôt GED traçable · versionning · archivage probant</div></div>
                </form>
            </div>
            @endcan
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Montant encaissé</div>
            <div class="h5">{{ $fmt($encaissement->montant) }} {{ $encaissement->devise }}</div>
            <hr>
            <div class="text-muted small text-uppercase">Taux de change</div>
            <div class="h6">{{ rtrim(rtrim(number_format((float) $encaissement->taux_change, 6, '.', ''), '0'), '.') }}</div>
            <hr>
            <div class="text-muted small text-uppercase">Contre-valeur XAF</div>
            <div class="h5 text-success">{{ $fmt($encaissement->montant_xaf) }} XAF</div>
            @if (in_array($encaissement->statut, ['saisi', 'rejete'], true) && $encaissement->gedDocuments->isEmpty() && $encaissement->pieces->isEmpty())
                <div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Aucun justificatif : la soumission est bloquée.</div>
            @endif
            @if ($encaissement->statut === 'rejete' && $encaissement->motif_rejet)
                <div class="alert alert-danger small mt-3 mb-0"><strong>Rejet</strong> ({{ \App\Support\WorkflowRejetCategorie::libelles()[$encaissement->categorie_rejet] ?? $encaissement->categorie_rejet }}) : {{ $encaissement->motif_rejet }}</div>
            @endif
        </div></div>

        @include('partials._timeline', ['instance' => $instance ?? null, 'etC' => $etC ?? []])

        @if ($peutAgir ?? false)
            @include('partials._workflow_agir', ['action' => route('encaissements.agir', $encaissement), 'avecRenvoi' => false])
        @endif
    </div>
</div>

@can('rapprocher', $encaissement)
<div class="modal fade" id="rappModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('encaissements.rapprocher', $encaissement) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Rapprochement bancaire</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Date de rapprochement <span class="text-danger">*</span></label>
                    <input type="date" name="date_rapprochement" class="form-control" required>
                </div>
                <div class="mb-1">
                    <label class="form-label">Référence bancaire</label>
                    <input type="text" name="reference_bancaire" value="{{ $encaissement->reference_bancaire }}" maxlength="100" class="form-control">
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Confirmer</button></div>
        </form>
    </div>
</div>
@endcan
@endsection
