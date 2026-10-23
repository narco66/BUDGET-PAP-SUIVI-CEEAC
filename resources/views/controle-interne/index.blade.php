@extends('layouts.app')
@section('titre', 'Contrôle interne (COSO)')
@php $ec = fn ($e) => \App\Models\ControleInterne::evaluationClasse($e); @endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Dispositif de contrôle interne (COSO)</h4>
        <span class="text-muted small">Cartographie des 5 composantes COSO et évaluation d'efficacité</span>
    </div>
    @can('controle_interne.creer')<a href="{{ route('controle-interne.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau contrôle</a>@endcan
</div>

{{-- Cartographie COSO : 5 composantes --}}
<div class="row g-3 mb-3">
    @foreach ($composantes as $cle => $libelle)
        @php $c = $parComposante[$cle]; @endphp
        <div class="col-lg">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
                <div class="small fw-semibold mb-1">{{ $libelle }}</div>
                <div class="fs-4 fw-bold">{{ $c['total'] }}</div>
                <div class="small">
                    <span class="text-success">●{{ $c['efficace'] }}</span>
                    <span class="text-warning">●{{ $c['partiel'] }}</span>
                    <span class="text-danger">●{{ $c['inefficace'] }}</span>
                    <span class="text-muted">●{{ $c['non_evalue'] }}</span>
                </div>
            </div></div>
        </div>
    @endforeach
</div>
<p class="text-muted small"><span class="text-success">● Efficace</span> · <span class="text-warning">● Partiel</span> · <span class="text-danger">● Inefficace</span> · <span class="text-muted">● Non évalué</span></p>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><select name="composante" class="form-select form-select-sm">
        <option value="">Toutes composantes</option>
        @foreach ($composantes as $k => $v)<option value="{{ $k }}" @selected(request('composante') === $k)>{{ $v }}</option>@endforeach
    </select></div>
    <div class="col-auto"><select name="evaluation" class="form-select form-select-sm">
        <option value="">Toutes évaluations</option>
        @foreach ($evaluations as $k => $v)<option value="{{ $k }}" @selected(request('evaluation') === $k)>{{ $v }}</option>@endforeach
    </select></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Composante COSO</th><th>Libellé</th><th>Type</th><th>Fréquence</th><th>Responsable</th><th>Évaluation</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($controles as $c)
            <tr>
                <td class="font-monospace small">{{ $c->code }}</td>
                <td class="small">{{ $composantes[$c->composante] ?? $c->composante }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($c->libelle, 32) }}</td>
                <td class="small">{{ \App\Models\ControleInterne::TYPES[$c->type] ?? $c->type }}</td>
                <td class="small">{{ \App\Models\ControleInterne::FREQUENCES[$c->frequence] ?? $c->frequence }}</td>
                <td class="small">{{ $c->responsable?->name ?? '—' }}</td>
                <td><span class="badge bg-{{ $ec($c->evaluation) }}">{{ $evaluations[$c->evaluation] ?? $c->evaluation }}</span></td>
                <td class="text-end text-nowrap">
                    @can('controle_interne.modifier')
                    <a href="{{ route('controle-interne.edit', $c) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('controle-interne.evaluer', $c) }}" class="d-inline">@csrf
                        <input type="hidden" name="evaluation" value="efficace">
                        <button class="btn btn-sm btn-outline-success py-0" title="Marquer efficace"><i class="bi bi-check2"></i></button>
                    </form>
                    @endcan
                </td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">Aucun contrôle interne cartographié.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $controles->links() }}</div>
@endsection
