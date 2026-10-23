@extends('layouts.app')
@section('titre', 'Modèles e-mail')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-envelope-paper me-2"></i>Modèles e-mail institutionnels</h4>
        <span class="text-muted small">Paramétrage MOA — variables @{{ cle }}</span>
    </div>
</div>

@if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Code</th><th>Libellé</th><th>Sujet</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($templates as $tpl)
                <tr>
                    <td><code>{{ $tpl->code }}</code></td>
                    <td>{{ $tpl->libelle }}</td>
                    <td class="small">{{ Str::limit($tpl->sujet, 60) }}</td>
                    <td>@if($tpl->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('admin.notifications.templates.edit', $tpl) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Aucun modèle — exécutez NotificationsAlertesSeeder.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($templates->hasPages())<div class="card-footer">{{ $templates->links() }}</div>@endif
</div>
@endsection
