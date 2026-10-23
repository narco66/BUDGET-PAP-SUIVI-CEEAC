@extends('layouts.app')
@section('titre', 'Circuits de validation')
@section('contenu')
<h4 class="fw-bold mb-3">Workflows documentaires</h4>
<div class="card border-0 shadow-sm">
    @foreach($workflows as $wf)
        <div class="card-body border-bottom">
            <strong>{{ $wf->libelle }}</strong> <span class="badge bg-secondary">{{ $wf->code }}</span>
            <div class="small mt-1">@foreach($wf->etapes as $e){{ $e->ordre }}. {{ $e->libelle }} → @endforeach</div>
        </div>
    @endforeach
    <div class="card-footer">{{ $workflows->links() }}</div>
</div>
@endsection
