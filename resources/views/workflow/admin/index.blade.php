@extends('layouts.app')
@section('titre', 'Circuits workflow D14')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2"></i>Circuits de validation institutionnels</h4>
    @can('workflow.create')
    <a href="{{ route('admin.workflows.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau circuit</a>
    @endcan
</div>

@if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Code</th><th>Libellé</th><th>Type objet</th><th>Étapes</th><th>Instances</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($workflows as $wf)
                <tr>
                    <td><code>{{ $wf->code }}</code></td>
                    <td>{{ $wf->libelle }}</td>
                    <td class="small text-muted">{{ $wf->type_objet }}</td>
                    <td>{{ $wf->etapes->count() }}</td>
                    <td>{{ $wf->instances_count }}</td>
                    <td>
                        @if ($wf->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif
                    </td>
                    <td class="text-end">
                        @can('workflow.update')
                        <a href="{{ route('admin.workflows.edit', $wf) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('admin.workflows.toggle', $wf) }}" method="POST" class="d-inline">@csrf
                            <button class="btn btn-sm btn-outline-secondary" title="Activer / désactiver"><i class="bi bi-toggle-{{ $wf->actif ? 'on' : 'off' }}"></i></button>
                        </form>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
