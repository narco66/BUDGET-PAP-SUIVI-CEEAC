@extends('layouts.app')
@section('titre', 'Piste d\'audit')

@section('contenu')
<form class="d-flex gap-2 mb-3" method="GET">
    <select name="action" class="form-select form-select-sm" style="width:180px;" onchange="this.form.submit()">
        <option value="">Toutes les actions</option>
        @foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action')===$a)>{{ $a }}</option>@endforeach
    </select>
    <select name="type" class="form-select form-select-sm" style="width:240px;" onchange="this.form.submit()">
        <option value="">Toutes les entités</option>
        @foreach ($types as $t)<option value="{{ $t }}" @selected(request('type')===$t)>{{ class_basename($t) }}</option>@endforeach
    </select>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Auteur</th><th>Rôle</th><th>Action</th><th>Entité</th><th>IP</th><th>URL</th></tr></thead>
            <tbody>
            @forelse ($journaux as $j)
                <tr @class(['table-danger' => $j->action === 'sod_violation'])>
                    <td class="small">{{ $j->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td class="small">{{ $j->user_nom ?? 'Système' }}</td>
                    <td class="small text-muted">{{ $j->role ?? '—' }}</td>
                    <td><span class="badge bg-{{ ['created'=>'success','updated'=>'warning','deleted'=>'danger','login'=>'info','logout'=>'secondary','sod_violation'=>'danger'][$j->action] ?? 'dark' }}">{{ $j->action }}</span></td>
                    <td class="small">{{ $j->auditable_type ? class_basename($j->auditable_type).' #'.$j->auditable_id : '—' }}</td>
                    <td class="small text-muted">{{ $j->ip }}</td>
                    <td class="small text-muted text-truncate" style="max-width:260px;">{{ $j->url }}</td>
                </tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-4">Aucune entrée.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $journaux->links() }}</div>
@endsection
