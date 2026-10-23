@extends('layouts.app')
@section('titre', 'Job en échec')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">{{ $job->nom }}</h4>
        <span class="text-muted small font-monospace">UUID {{ $job->uuid }} · file {{ $job->queue }}</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('administration-technique.queues.index', ['file' => $job->queue]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
        @canany(['admin_technique.monitor', 'admin_technique.admin'])
        <form method="POST" action="{{ route('administration-technique.queues.retry', $job->uuid) }}">@csrf<button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Relancer</button></form>
        @endcanany
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Métadonnées</div>
            <div class="card-body small">
                <dl class="mb-0">
                    <dt class="text-muted">Connexion</dt><dd class="font-monospace">{{ $job->connection }}</dd>
                    <dt class="text-muted">File</dt><dd class="font-monospace">{{ $job->queue }}</dd>
                    <dt class="text-muted">Échec le</dt><dd>{{ $job->failed_at }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold text-danger"><i class="bi bi-bug me-1"></i>Exception</div>
            <div class="card-body p-0">
                <pre class="small mb-0 p-3 bg-light overflow-auto" style="max-height:480px">{{ $job->exception_lignes }}</pre>
            </div>
        </div>
    </div>
</div>
@endsection
