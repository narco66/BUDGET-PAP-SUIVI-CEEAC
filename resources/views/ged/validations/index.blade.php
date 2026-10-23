@extends('layouts.app')
@section('titre', 'Validations en attente')
@section('contenu')
<h4 class="fw-bold mb-3">Validations en attente</h4>
<div class="card border-0 shadow-sm">
    @forelse($validations as $val)
        <div class="card-body border-bottom d-flex justify-content-between align-items-center">
            <div>
                <a href="{{ route('ged.documents.show', $val->document) }}">{{ $val->document?->reference }}</a>
                <div class="small text-muted">{{ $val->etape?->libelle }}</div>
            </div>
            <div class="btn-group btn-group-sm">
                <form method="POST" action="{{ route('ged.validations.valider', $val) }}">@csrf<button class="btn btn-success">Valider</button></form>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejeter-{{ $val->id }}">Rejeter</button>
            </div>
        </div>
        <div class="modal fade" id="rejeter-{{ $val->id }}"><div class="modal-dialog"><form method="POST" action="{{ route('ged.validations.rejeter', $val) }}" class="modal-content">@csrf
            <div class="modal-header"><h5 class="modal-title">Rejeter</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><textarea name="motif" class="form-control" required placeholder="Motif"></textarea></div>
            <div class="modal-footer"><button class="btn btn-danger">Confirmer</button></div>
        </form></div></div>
    @empty
        <div class="card-body text-muted">Aucune validation en attente</div>
    @endforelse
    <div class="card-footer">{{ $validations->links() }}</div>
</div>
@endsection
