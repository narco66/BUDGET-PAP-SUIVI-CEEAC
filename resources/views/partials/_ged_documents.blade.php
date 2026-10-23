{{-- Widget GED : documents rattachés à un objet métier --}}
{{-- Variables : $attachable (Model), $type (alias module: besoin|engagement|pap|...) --}}
@inject('gedAttach', 'App\Services\Ged\GedAttachmentService')
@php $gedDocuments = $gedAttach->lister($attachable, $type); @endphp

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-folder2-open me-1"></i>Documents GED ({{ $gedDocuments->count() }})</span>
        @can('ged.voir')
        <a href="{{ route('ged.documents.index', ['module_source' => \App\Services\Ged\GedAttachmentService::codeModule($type)]) }}" class="btn btn-sm btn-outline-primary">Voir tout</a>
        @endcan
    </div>
    <ul class="list-group list-group-flush">
        @forelse ($gedDocuments as $doc)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div class="small">
                    <a href="{{ route('ged.documents.show', $doc) }}" class="fw-semibold">{{ $doc->titre }}</a>
                    <div class="text-muted font-monospace">{{ $doc->reference }}</div>
                    <span class="badge bg-secondary-subtle text-secondary">{{ $doc->statut?->libelle() }}</span>
                    <span class="text-muted"> · {{ $doc->createur?->name }} · {{ $doc->date_depot?->format('d/m/Y') }}</span>
                </div>
                <div class="btn-group btn-group-sm">
                    @can('download', $doc)
                    <a href="{{ route('ged.documents.download', $doc) }}" class="btn btn-outline-secondary" title="Télécharger"><i class="bi bi-download"></i></a>
                    @endcan
                </div>
            </li>
        @empty
            <li class="list-group-item text-muted small">Aucun document GED rattaché.</li>
        @endforelse
    </ul>
    @can('ged.creer')
    <div class="card-body border-top">
        <form action="{{ route('ged.attach.store', [$type, $attachable->id]) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small mb-0">Fichier GED</label>
                    <input type="file" name="fichier" class="form-control form-control-sm" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt,.csv">
                </div>
                <div class="col-md-5">
                    <label class="form-label small mb-0">Titre (optionnel)</label>
                    <input type="text" name="titre" class="form-control form-control-sm" placeholder="Titre du document">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-cloud-upload"></i> Déposer</button>
                </div>
            </div>
            <div class="form-text">Dépôt GED traçable · versionning · archivage probant</div>
        </form>
    </div>
    @endcan
</div>
