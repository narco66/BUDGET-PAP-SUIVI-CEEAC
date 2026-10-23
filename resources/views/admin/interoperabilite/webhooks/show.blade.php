@extends('layouts.app')
@section('titre', $webhook->libelle)

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-2">
    <div>
        <h4 class="fw-bold mb-0">{{ $webhook->libelle }}</h4>
        <code class="small">{{ $webhook->evenement }}</code>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('admin.interoperabilite.webhooks.tester', $webhook) }}">@csrf<button class="btn btn-sm btn-outline-primary"><i class="bi bi-lightning me-1"></i>Test en ligne</button></form>
        <a href="{{ route('admin.interoperabilite.webhooks.edit', $webhook) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
    </div>
</div>
@include('admin.interoperabilite._nav-module', ['page' => 'webhooks'])

@if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif

<div class="card border-0 shadow-sm mb-3"><div class="card-body small">
    <strong>URL :</strong> {{ $webhook->url }} ({{ $webhook->methode ?? 'POST' }})<br>
    <strong>Application :</strong> {{ $webhook->connectedApp?->code ?? '—' }} ·
    <strong>Statut :</strong> @if($webhook->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif
</div></div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white"><strong>Journal des livraisons</strong></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Statut</th><th>HTTP</th><th>Tentatives</th><th>Prochaine</th><th>Erreur</th></tr></thead>
            <tbody>
            @forelse($livraisons as $liv)
                <tr>
                    <td class="small">{{ $liv->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>
                        @if($liv->statut === 'livre')<span class="badge bg-success">livré</span>
                        @elseif($liv->statut === 'echec')<span class="badge bg-danger">échec</span>
                        @else<span class="badge bg-warning text-dark">en attente</span>@endif
                    </td>
                    <td>{{ $liv->statut_http ?? '—' }}</td>
                    <td>{{ $liv->tentatives }}</td>
                    <td class="small">{{ $liv->prochaine_tentative_le?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="small text-muted">{{ Str::limit($liv->erreur, 60) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">Aucune livraison.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($livraisons->hasPages())<div class="card-footer">{{ $livraisons->links() }}</div>@endif
</div>
@endsection
