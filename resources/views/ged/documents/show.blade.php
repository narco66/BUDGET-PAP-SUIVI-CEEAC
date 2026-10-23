@extends('layouts.app')
@section('titre', $document->titre)

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1">{{ $document->titre }}</h4>
        <div class="text-muted small font-monospace">{{ $document->reference }}</div>
    </div>
    <div class="btn-group btn-group-sm">
        @can('download', $document)
        <a href="{{ route('ged.documents.download', $document) }}" class="btn btn-outline-primary"><i class="bi bi-download me-1"></i>Télécharger</a>
        @endcan
        @can('preview', $document)
        <a href="{{ route('ged.documents.preview', $document) }}" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-eye me-1"></i>Prévisualiser</a>
        @endcan
        @can('ged.exporter')
        <div class="btn-group">
            <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">Exports PDF</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('ged.exports.documents.fiche', $document) }}">Fiche documentaire</a></li>
                <li><a class="dropdown-item" href="{{ route('ged.exports.documents.certificat_integrite', $document) }}">Certificat d'intégrité</a></li>
                <li><a class="dropdown-item" href="{{ route('ged.exports.documents.bordereau_depot', $document) }}">Bordereau de dépôt</a></li>
                @if($document->est_signe)
                <li><a class="dropdown-item" href="{{ route('ged.exports.documents.certificat_signature', $document) }}">Certificat de signature</a></li>
                @endif
                @if($document->est_archive)
                <li><a class="dropdown-item" href="{{ route('ged.exports.documents.bordereau_archivage', $document) }}">Bordereau d'archivage</a></li>
                @endif
            </ul>
        </div>
        @endcan
        @can('update', $document)
        <a href="{{ route('ged.documents.edit', $document) }}" class="btn btn-outline-dark"><i class="bi bi-pencil me-1"></i>Modifier</a>
        @endcan
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2 small">
                    <div class="col-md-4"><strong>Statut</strong><br><span class="badge bg-secondary">{{ $document->statut?->libelle() }}</span></div>
                    <div class="col-md-4"><strong>Confidentialité</strong><br>{{ $document->niveau_confidentialite?->libelle() }}</div>
                    <div class="col-md-4"><strong>Version</strong><br>v{{ $document->version_courante }}</div>
                    <div class="col-md-4"><strong>Type</strong><br>{{ $document->typeDocument?->libelle ?? '—' }}</div>
                    <div class="col-md-4"><strong>Catégorie</strong><br>{{ $document->categorie?->libelle ?? '—' }}</div>
                    <div class="col-md-4"><strong>Dossier</strong><br>{{ $document->dossier?->titre ?? '—' }}</div>
                    <div class="col-md-4"><strong>Dépôt</strong><br>{{ $document->date_depot?->format('d/m/Y H:i') }}</div>
                    <div class="col-md-4"><strong>Auteur</strong><br>{{ $document->createur?->name }}</div>
                    <div class="col-md-4"><strong>SHA-256</strong><br><code class="small">{{ Str::limit($document->hash_sha256, 16) }}…</code></div>
                </div>
                @if($document->description)
                <hr><p class="mb-0">{{ $document->description }}</p>
                @endif
                @if($document->tags->isNotEmpty())
                <hr>@foreach($document->tags as $tag)<span class="badge bg-light text-dark me-1">{{ $tag->nom }}</span>@endforeach
                @endif
            </div>
        </div>

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#versions">Versions</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#signatures">Signatures</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#validations">Validations</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#journal">Journal</button></li>
        </ul>
        <div class="tab-content card border-top-0 shadow-sm">
            <div class="tab-pane fade show active p-3" id="versions">
                <table class="table table-sm">
                    <thead><tr><th>v</th><th>Fichier</th><th>Hash</th><th>Auteur</th><th>Date</th></tr></thead>
                    <tbody>
                    @foreach($document->versions as $v)
                        <tr>
                            <td>{{ $v->numero }}</td>
                            <td>{{ $v->nom_original }}</td>
                            <td class="small"><code>{{ Str::limit($v->hash_sha256, 12) }}</code></td>
                            <td>{{ $v->auteur?->name }}</td>
                            <td class="small">{{ $v->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @can('update', $document)
                <form method="POST" action="{{ route('ged.documents.version', $document) }}" enctype="multipart/form-data" class="border-top pt-3">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5"><input type="file" name="fichier" class="form-control form-control-sm" required></div>
                        <div class="col-md-5"><input type="text" name="commentaire" class="form-control form-control-sm" placeholder="Commentaire de version"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Nouvelle version</button></div>
                    </div>
                </form>
                @endcan
            </div>
            <div class="tab-pane fade p-3" id="signatures">
                @forelse($document->signatures as $sig)
                    <div class="border-bottom pb-2 mb-2 small">
                        {{ $sig->signataire?->name }} — <span class="badge bg-secondary">{{ $sig->statut }}</span>
                        @if($sig->date_signature) · {{ $sig->date_signature->format('d/m/Y H:i') }}@endif
                    </div>
                @empty
                    <p class="text-muted small">Aucune signature</p>
                @endforelse
            </div>
            <div class="tab-pane fade p-3" id="validations">
                @forelse($document->validations as $val)
                    <div class="border-bottom pb-2 mb-2 small">{{ $val->validateur?->name }} — {{ $val->statut }}</div>
                @empty
                    <p class="text-muted small">Aucune validation</p>
                @endforelse
            </div>
            <div class="tab-pane fade p-3" id="journal">
                @foreach($document->journaux->take(20) as $j)
                    <div class="small border-bottom pb-1 mb-1"><strong>{{ $j->action }}</strong> · {{ $j->utilisateur?->name }} · {{ $j->created_at->format('d/m/Y H:i') }}</div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Intégrité & signature</div>
            <div class="card-body small">
                <div class="mb-2">Intégrité SHA-256 : @if($verification['integrite'])<span class="text-success"><i class="bi bi-check-circle"></i> OK</span>@else<span class="text-danger">Compromis</span>@endif</div>
                <div class="mb-2">Signé : {{ $document->est_signe ? 'Oui' : 'Non' }}</div>
                <a href="{{ route('verification.ged', $document->uuid) }}" class="btn btn-sm btn-outline-primary w-100" target="_blank">Page de vérification publique</a>
            </div>
        </div>

        @can('share', $document)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Partager</div>
            <div class="card-body">
                <form method="POST" action="{{ route('ged.documents.partager', $document) }}">@csrf
                    <select name="utilisateur_id" class="form-select form-select-sm mb-2" required>
                        <option value="">Utilisateur…</option>
                        @foreach(\App\Models\User::where('actif', true)->orderBy('name')->limit(50)->get() as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="commentaire" class="form-control form-control-sm mb-2" placeholder="Commentaire">
                    <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-share me-1"></i>Partager</button>
                </form>
            </div>
        </div>
        @endcan

        @can('archive', $document)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Archivage</div>
            <div class="card-body">
                <form method="POST" action="{{ route('ged.documents.archiver', $document) }}">@csrf
                    <input type="text" name="motif" class="form-control form-control-sm mb-2" placeholder="Motif d'archivage" required>
                    <button class="btn btn-sm btn-warning w-100"><i class="bi bi-archive me-1"></i>Archiver</button>
                </form>
            </div>
        </div>
        @endcan

        @can('restore', $document)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('ged.documents.restaurer', $document) }}">@csrf
                    <input type="text" name="motif" class="form-control form-control-sm mb-2" placeholder="Motif de restauration" required>
                    <button class="btn btn-sm btn-success w-100">Restaurer</button>
                </form>
            </div>
        </div>
        @endcan

        @can('delete', $document)
        <div class="card border-0 shadow-sm border-danger">
            <div class="card-body">
                <form method="POST" action="{{ route('ged.documents.destroy', $document) }}" onsubmit="return confirm('Déplacer en corbeille ?')">@csrf @method('DELETE')
                    <input type="text" name="motif" class="form-control form-control-sm mb-2" placeholder="Motif (optionnel)">
                    <button class="btn btn-sm btn-outline-danger w-100">Supprimer (corbeille)</button>
                </form>
            </div>
        </div>
        @endcan
    </div>
</div>
@endsection
