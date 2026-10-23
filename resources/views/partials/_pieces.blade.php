{{-- $attachable (modèle avec pièces), $type (alias: besoin|engagement) --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold"><i class="bi bi-paperclip me-1"></i>Pièces justificatives ({{ $attachable->pieces->count() }})</div>
    <ul class="list-group list-group-flush">
        @forelse ($attachable->pieces as $piece)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div class="small">
                    <i class="bi bi-file-earmark-text me-1"></i>{{ $piece->nom }}
                    <span class="badge bg-light text-dark">{{ $piece->categorie }}</span>
                    <div class="text-muted">{{ number_format($piece->taille/1024, 0) }} Ko · {{ $piece->auteur?->name }} · {{ $piece->created_at->format('d/m/Y') }}</div>
                </div>
                <div class="btn-group btn-group-sm">
                    @can('ged.voir')<a href="{{ route('pieces.download', $piece) }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i></a>@endcan
                    @can('ged.supprimer')<form action="{{ route('pieces.destroy', $piece) }}" method="POST" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form>@endcan
                </div>
            </li>
        @empty<li class="list-group-item text-muted small">Aucune pièce.</li>@endforelse
    </ul>
    @can('ged.creer')
    <div class="card-body">
        <form action="{{ route('pieces.store', [$type, $attachable->id]) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
            @csrf
            <input type="file" name="fichier" class="form-control form-control-sm" required>
            <input type="text" name="categorie" class="form-control form-control-sm" placeholder="Catégorie" style="max-width:130px;">
            <button class="btn btn-sm btn-primary"><i class="bi bi-upload"></i></button>
        </form>
    </div>
    @endcan
</div>
