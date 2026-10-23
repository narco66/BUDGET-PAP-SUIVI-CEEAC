@extends('layouts.app')
@section('titre', $connaissance->titre)

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
    <div>
        <a href="{{ route('ged.connaissances.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Base de connaissances</a>
        <h4 class="fw-bold mb-1">{{ $connaissance->titre }}</h4>
        <div class="small text-muted">
            <span class="font-monospace">{{ $connaissance->reference }}</span> ·
            {{ \App\Models\Ged\Connaissance::TYPES[$connaissance->type] ?? $connaissance->type }} ·
            {{ \App\Models\Ged\Connaissance::MODULES[$connaissance->module] ?? $connaissance->module }} · v{{ $connaissance->version }}
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($connaissance->estModifiablePar(auth()->user()))
            <a href="{{ route('ged.connaissances.edit', $connaissance) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>
        @endif
        @can('ged.publier')
            @if($connaissance->statut !== 'publie')
            <form method="POST" action="{{ route('ged.connaissances.publier', $connaissance) }}">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Publier</button></form>
            @endif
            @if($connaissance->statut !== 'archive')
            <form method="POST" action="{{ route('ged.connaissances.archiver', $connaissance) }}" onsubmit="return confirm('Archiver ce contenu ?')">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-archive me-1"></i>Archiver</button></form>
            @endif
        @endcan
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                @if($connaissance->resume)
                    <p class="lead text-muted border-bottom pb-2 mb-3">{{ $connaissance->resume }}</p>
                @endif
                <div class="connaissance-contenu">
                    {!! \Illuminate\Support\Str::markdown($connaissance->contenu) !!}
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Métadonnées</div>
            <div class="card-body small">
                <dl class="mb-0">
                    <dt class="text-muted">Statut</dt>
                    <dd><span class="badge bg-{{ $connaissance->statut === 'publie' ? 'success' : ($connaissance->statut === 'archive' ? 'secondary' : 'warning') }}">{{ \App\Models\Ged\Connaissance::STATUTS[$connaissance->statut] ?? $connaissance->statut }}</span></dd>
                    <dt class="text-muted">Auteur</dt><dd>{{ $connaissance->auteur?->name ?? '—' }}</dd>
                    @if($connaissance->publie_le)
                    <dt class="text-muted">Publié le</dt><dd>{{ $connaissance->publie_le->format('d/m/Y H:i') }} @if($connaissance->publiePar) par {{ $connaissance->publiePar->name }}@endif</dd>
                    @endif
                    @if($connaissance->tags)
                    <dt class="text-muted">Tags</dt><dd>@foreach($connaissance->tags as $tag)<span class="badge bg-light text-dark me-1">{{ $tag }}</span>@endforeach</dd>
                    @endif
                    @if($connaissance->documentGed)
                    <dt class="text-muted">Document GED</dt>
                    <dd><a href="{{ route('ged.documents.show', $connaissance->documentGed) }}">{{ $connaissance->documentGed->titre }}</a></dd>
                    @endif
                </dl>
            </div>
        </div>
        @if($connaissance->versions->isNotEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Historique des versions</div>
            <ul class="list-group list-group-flush small">
                @foreach ($connaissance->versions->take(8) as $v)
                <li class="list-group-item d-flex justify-content-between">
                    <span>v{{ $v->version }}</span>
                    <span class="text-muted">{{ $v->auteur?->name }} · {{ $v->created_at?->format('d/m/Y') }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
.connaissance-contenu h1,.connaissance-contenu h2,.connaissance-contenu h3 { font-size: 1.1rem; font-weight: 600; margin-top: 1rem; }
.connaissance-contenu ul,.connaissance-contenu ol { padding-left: 1.25rem; }
.connaissance-contenu pre { background: #f8f9fa; padding: .75rem; border-radius: .25rem; font-size: .85rem; }
</style>
@endpush
