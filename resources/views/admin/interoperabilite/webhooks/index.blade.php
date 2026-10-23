@extends('layouts.app')
@section('titre', 'Webhooks')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-broadcast me-2"></i>Abonnements webhooks</h4>
    <a href="{{ route('admin.interoperabilite.webhooks.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau webhook</a>
</div>
@include('admin.interoperabilite._nav-module', ['page' => 'webhooks'])

@if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif

<form method="GET" class="mb-3">
    <select name="evenement" class="form-select form-select-sm" style="max-width:320px;" onchange="this.form.submit()">
        <option value="">Tous les événements</option>
        @foreach($evenements as $evt)
            <option value="{{ $evt->code }}" @selected(request('evenement')===$evt->code)>{{ $evt->code }} — {{ $evt->libelle }}</option>
        @endforeach
    </select>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Libellé</th><th>Événement</th><th>URL</th><th>App</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($webhooks as $wh)
                <tr>
                    <td>{{ $wh->libelle }}</td>
                    <td><code class="small">{{ $wh->evenement }}</code></td>
                    <td class="small text-truncate" style="max-width:220px;">{{ $wh->url }}</td>
                    <td class="small">{{ $wh->connectedApp?->code ?? '—' }}</td>
                    <td>@if($wh->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('admin.interoperabilite.webhooks.show', $wh) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.interoperabilite.webhooks.edit', $wh) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Aucun webhook — créez un abonnement ou exécutez InteroperabiliteApiSeeder.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($webhooks->hasPages())<div class="card-footer">{{ $webhooks->links() }}</div>@endif
</div>
@endsection
