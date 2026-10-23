@extends('layouts.app')
@section('titre', 'GED · '.$label)

@section('contenu')
<div class="mb-3">
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h1 class="h5 mb-1">{{ $label }}</h1>
        <p class="mb-0">
            @if(method_exists($attachable, 'code'))
                <code>{{ $attachable->code }}</code>
            @endif
            <strong>{{ $attachable->intitule ?? $attachable->libelle ?? '' }}</strong>
        </p>
    </div>
</div>

@include('partials._ged_documents', ['attachable' => $attachable, 'type' => $type])
@endsection
