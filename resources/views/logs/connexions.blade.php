@extends('layouts.app')
@section('titre', 'Journal des connexions')
@section('contenu')
<form method="GET" class="d-flex gap-2 mb-3">
    <input name="email" value="{{ request('email') }}" class="form-control form-control-sm" placeholder="E-mail…" style="width:240px;">
    <select name="succes" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
        <option value="">Tous</option>
        <option value="1" @selected(request('succes')==='1')>Succès</option>
        <option value="0" @selected(request('succes')==='0')>Échecs</option>
    </select>
    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
</form>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Utilisateur</th><th>E-mail</th><th class="text-center">Résultat</th><th>Motif</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="small">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                <td class="small">{{ $log->user?->name ?? '—' }}</td>
                <td class="small">{{ $log->email }}</td>
                <td class="text-center">@if($log->succes)<span class="badge bg-success">succès</span>@else<span class="badge bg-danger">échec</span>@endif</td>
                <td class="small text-muted">{{ $log->motif }}</td>
                <td class="small text-muted">{{ $log->ip }}</td>
            </tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-4">Aucune connexion enregistrée.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
