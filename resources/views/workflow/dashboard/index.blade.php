@extends('layouts.app')
@section('titre', 'Performance workflow')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2"></i>Performance des circuits de validation</h4>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <select name="jours" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ([30, 90, 180, 365] as $p)
                <option value="{{ $p }}" @selected($jours === $p)>{{ $p }} jours</option>
                @endforeach
            </select>
        </form>
        @can('workflow.audit')
        <a href="{{ route('workflow.dashboard.export', ['jours' => $jours]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export CSV</a>
        @endcan
    </div>
</div>

@php $k = $data['kpi']; @endphp
<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">En cours</div><div class="h4 mb-0">{{ $k['en_cours'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm border-start border-4 border-danger"><div class="card-body text-center"><div class="text-muted small">En retard</div><div class="h4 mb-0 text-danger">{{ $k['en_retard'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Validés</div><div class="h4 mb-0 text-success">{{ $k['valides_periode'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Rejetés</div><div class="h4 mb-0 text-warning">{{ $k['rejetes_periode'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Taux rejet</div><div class="h4 mb-0">{{ $k['taux_rejet_pct'] }} %</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Visas émis</div><div class="h4 mb-0">{{ $k['visas_emis'] }}</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Par circuit</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Circuit</th><th class="text-end">En cours</th><th class="text-end">Validés</th><th class="text-end">Rejetés</th></tr></thead>
                <tbody>
                @foreach ($data['par_circuit'] as $wf)
                    <tr>
                        <td><code>{{ $wf->code }}</code> <span class="text-muted small">{{ $wf->libelle }}</span></td>
                        <td class="text-end">{{ $wf->en_cours_count }}</td>
                        <td class="text-end">{{ $wf->valides_count }}</td>
                        <td class="text-end">{{ $wf->rejetes_count }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Dernières actions</div>
            <ul class="list-group list-group-flush small">
                @forelse ($data['dernieres_actions'] as $a)
                    <li class="list-group-item d-flex justify-content-between">
                        <span><span class="badge bg-dark">{{ $a->action }}</span> {{ $a->workflow_code }}</span>
                        <span class="text-muted">{{ $a->created_at?->format('d/m H:i') }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Aucune action.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
