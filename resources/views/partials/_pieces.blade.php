{{-- $attachable (modèle avec pièces), $type (alias: besoin|engagement) --}}
@php
    $utilisateur = auth()->user();
    $peutAjouter = \App\Http\Controllers\PieceController::peutAjouter($utilisateur, $attachable);
    $peutVoir = \App\Http\Controllers\PieceController::peutVoir($utilisateur, $attachable);
@endphp
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
                    @if ($peutVoir)<a href="{{ route('pieces.download', $piece) }}" class="btn btn-outline-secondary" title="Télécharger"><i class="bi bi-download"></i></a>@endif
                    @if (\App\Http\Controllers\PieceController::peutSupprimer($utilisateur, $piece))<form action="{{ route('pieces.destroy', $piece) }}" method="POST" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form>@endif
                </div>
            </li>
        @empty<li class="list-group-item text-muted small">Aucune pièce.</li>@endforelse
    </ul>
    {{-- Pièces des étapes précédentes de la chaîne de la dépense (consultation seule). --}}
    @foreach (\App\Http\Controllers\PieceController::dossiersAmont($attachable) as [$etape, $dossier])
        @if ($dossier->pieces->isNotEmpty())
            @php $peutVoirAmont = \App\Http\Controllers\PieceController::peutVoir($utilisateur, $dossier); @endphp
            <div class="card-header bg-light small fw-semibold border-top"><i class="bi bi-arrow-return-right me-1"></i>{{ $etape }} {{ $dossier->numero }} ({{ $dossier->pieces->count() }})</div>
            <ul class="list-group list-group-flush">
                @foreach ($dossier->pieces as $piece)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="small">
                            <i class="bi bi-file-earmark-text me-1"></i>{{ $piece->nom }}
                            <span class="badge bg-light text-dark">{{ $piece->categorie }}</span>
                            <div class="text-muted">{{ number_format($piece->taille/1024, 0) }} Ko · {{ $piece->auteur?->name }} · {{ $piece->created_at->format('d/m/Y') }}</div>
                        </div>
                        @if ($peutVoirAmont)<a href="{{ route('pieces.download', $piece) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger"><i class="bi bi-download"></i></a>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endforeach
    @if ($peutAjouter)
    <div class="card-body">
        <form action="{{ route('pieces.store', [$type, $attachable->id]) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
            @csrf
            <input type="file" name="fichier" class="form-control form-control-sm @error('fichier') is-invalid @enderror" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="min-width:220px; flex:1;">
            <input type="text" name="categorie" class="form-control form-control-sm" placeholder="Catégorie (devis, facture…)" style="max-width:200px;">
            <button class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Joindre</button>
            @error('fichier')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </form>
        <div class="form-text">PDF, image, Word ou Excel — 10 Mo maximum.</div>
    </div>
    @endif
</div>
