{{-- $attachable, $type (plan|vision) --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold"><i class="bi bi-folder2-open me-1"></i>Documents stratégiques ({{ $attachable->pieces->count() }})</div>
    <ul class="list-group list-group-flush">
        @forelse ($attachable->pieces as $piece)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div class="small">
                    <i class="bi bi-file-earmark-text me-1"></i>{{ $piece->nom }}
                    <span class="badge bg-light text-dark">{{ $piece->categorie }}</span>
                    <div class="text-muted">{{ number_format($piece->taille/1024, 0) }} Ko · {{ $piece->auteur?->name }} · {{ $piece->created_at->format('d/m/Y') }}</div>
                </div>
                <div class="btn-group btn-group-sm">
                    @can('strategie.view')<a href="{{ route('strategie.documents.download', $piece) }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i></a>@endcan
                    @can('strategie.update')<form action="{{ route('strategie.documents.destroy', $piece) }}" method="POST" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form>@endcan
                </div>
            </li>
        @empty<li class="list-group-item text-muted small">Aucun document.</li>@endforelse
    </ul>
    @can('strategie.update')
    <div class="card-body">
        <form action="{{ route('strategie.documents.store', [$type, $attachable->id]) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
            @csrf
            <input type="file" name="fichier" class="form-control form-control-sm" required style="max-width:280px;">
            <select name="categorie" class="form-select form-select-sm" style="max-width:160px;">
                @foreach(['document_officiel','note_technique','rapport','annexe'] as $cat)<option value="{{ $cat }}">{{ str_replace('_', ' ', ucfirst($cat)) }}</option>@endforeach
            </select>
            <button class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Ajouter</button>
        </form>
    </div>
    @endcan
</div>
