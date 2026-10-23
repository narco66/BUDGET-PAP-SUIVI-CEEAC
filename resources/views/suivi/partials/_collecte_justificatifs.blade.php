{{-- Justificatifs par collecte (phase S1) — widget compact --}}
@inject('gedAttach', 'App\Services\Ged\GedAttachmentService')
@php
    $docs = $gedAttach->lister($collecte, 'collecte');
    $nbPieces = $collecte->pieces()->count();
    $total = $docs->count() + $nbPieces;
@endphp
<div class="border rounded p-2 bg-white small">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="fw-semibold"><i class="bi bi-paperclip me-1"></i>Justificatifs ({{ $total }})</span>
        @if($total === 0 && config('suivi.justificatif_obligatoire'))
            <span class="badge bg-warning text-dark">Requis pour validation</span>
        @endif
    </div>
    @if($docs->isNotEmpty() || $nbPieces > 0)
        <ul class="list-unstyled mb-2 ps-2">
            @foreach($docs as $doc)
                <li><a href="{{ route('ged.documents.show', $doc) }}" class="small">{{ $doc->titre }}</a></li>
            @endforeach
            @foreach($collecte->pieces as $p)
                <li class="text-muted">{{ $p->nom }} (pièce)</li>
            @endforeach
        </ul>
    @else
        <p class="text-muted mb-2">Aucun justificatif rattaché.</p>
    @endif
    @can('ged.creer')
        @if($collecte->statut !== 'validee')
        <form action="{{ route('ged.attach.store', ['collecte', $collecte->id]) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-1 align-items-center">
            @csrf
            <input type="file" name="fichier" class="form-control form-control-sm" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt,.csv">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i></button>
        </form>
        @endif
    @endcan
</div>
