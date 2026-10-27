@extends('layouts.app')
@section('titre', 'Historique utilisateur')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-person-lines-fill me-2"></i>Interventions de {{ $cible->name }}</h4>
        <span class="text-muted small">{{ $cible->getRoleNames()->implode(', ') }} · {{ $journaux->total() }} événement(s) dans votre périmètre de consultation</span>
    </div>
    <a href="{{ route('audit.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Journal</a>
</div>
@include('audit._table', ['journaux' => $journaux])
@endsection
