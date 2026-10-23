@extends('layouts.app')
@section('titre', 'Utilisateurs')
@php $sc = ['actif'=>'success','suspendu'=>'warning','desactive'=>'secondary']; @endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2" method="GET">
        <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Nom, e-mail, matricule…" style="width:240px;">
        <select name="statut" class="form-select form-select-sm" style="width:150px;" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            @foreach (['actif'=>'Actif','suspendu'=>'Suspendu','desactive'=>'Désactivé'] as $k=>$v)<option value="{{ $k }}" @selected(request('statut')===$k)>{{ $v }}</option>@endforeach
        </select>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    @can('utilisateur.creer')<a href="{{ route('utilisateurs.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Nouvel utilisateur</a>@endcan
</div>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th></th><th>Nom</th><th>E-mail</th><th>Structure</th><th>Rôles</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($utilisateurs as $u)
            <tr>
                <td style="width:42px;">
                    @if($u->avatar)<img src="{{ \Illuminate\Support\Facades\Storage::url($u->avatar) }}" class="rounded-circle" width="34" height="34" style="object-fit:cover;">
                    @else<span class="rounded-circle bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center justify-content-center" style="width:34px;height:34px;">{{ strtoupper(substr($u->name,0,1)) }}</span>@endif
                </td>
                <td>{{ $u->name }}<br><small class="text-muted">{{ $u->matricule ?? '—' }}</small></td>
                <td class="small">{{ $u->email }}</td>
                <td class="small text-muted">{{ $u->structure?->sigle ?? '—' }}</td>
                <td>@foreach ($u->roles->take(3) as $r)<span class="badge bg-primary-subtle text-primary-emphasis">{{ $r->libelle ?? $r->name }}</span> @endforeach @if($u->roles->count()>3)<span class="badge bg-light text-dark">+{{ $u->roles->count()-3 }}</span>@endif</td>
                <td class="text-center"><span class="badge bg-{{ $sc[$u->statut] ?? 'secondary' }}">{{ $u->statut }}</span></td>
                <td class="text-end">
                    @can('utilisateur.voir')<a href="{{ route('utilisateurs.show', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan
                    @can('utilisateur.modifier')<a href="{{ route('utilisateurs.edit', $u) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                </td>
            </tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-4">Aucun utilisateur.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $utilisateurs->links() }}</div>
@endsection
