@extends('layouts.app')
@section('titre', 'Modifier PAP '.$pap->reference)

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Modifier {{ $pap->reference }}</h1>
    <a href="{{ route('paps.show', $pap) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form action="{{ route('paps.update', $pap) }}" method="POST">
            @csrf @method('PUT')
            @include('paps._form', ['pap' => $pap])
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endsection
