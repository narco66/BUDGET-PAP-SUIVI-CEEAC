@extends('layouts.app')
@section('titre', 'Historique du dossier')

@section('contenu')
@use('App\Support\Horodatage')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Historique : {{ $type }} #{{ $id }}</h4>
        <span class="text-muted small">Chronologie consolidée : journal central, décisions du workflow, tâches, visas · {{ $chronologie->count() }} événement(s) · heures à {{ Horodatage::fuseau() }}</span>
    </div>
    <a href="{{ route('audit.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Journal</a>
</div>

<div class="card border-0 shadow-sm"><div class="card-body">
    @forelse ($chronologie as $e)
        <div class="d-flex gap-3 border-bottom py-2">
            <div class="text-muted small text-nowrap" style="min-width:150px" title="{{ Horodatage::utc($e['instant']) }}">{{ Horodatage::local($e['instant']) }}</div>
            <div class="flex-grow-1 small">
                <span class="badge text-bg-light border me-1">{{ $e['source'] }}</span>
                @if ($e['id'])<a href="{{ route('audit.show', $e['id']) }}"><code class="small">{{ $e['code'] }}</code></a>@else<code class="small">{{ $e['code'] }}</code>@endif
                · {{ $e['acteur'] }}
                @if ($e['detail'])<div class="text-muted">{{ $e['detail'] }}</div>@endif
            </div>
        </div>
    @empty
        <p class="text-muted small mb-0">Aucun événement consultable pour ce dossier.</p>
    @endforelse
</div></div>
@endsection
