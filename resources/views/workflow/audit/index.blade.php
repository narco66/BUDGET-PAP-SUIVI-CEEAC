@extends('layouts.app')
@section('titre', 'Audit workflow')

@section('contenu')
<h4 class="fw-bold mb-3"><i class="bi bi-journal-check me-2"></i>Journal des actions workflow</h4>

<form class="d-flex gap-2 mb-3 flex-wrap" method="GET">
    <select name="action" class="form-select form-select-sm" style="width:200px;" onchange="this.form.submit()">
        <option value="">Toutes les actions</option>
        @foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action')===$a)>{{ $a }}</option>@endforeach
    </select>
    <select name="workflow" class="form-select form-select-sm" style="width:200px;" onchange="this.form.submit()">
        <option value="">Tous les circuits</option>
        @foreach ($circuits as $c)<option value="{{ $c }}" @selected(request('workflow')===$c)>{{ $c }}</option>@endforeach
    </select>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Action</th><th>Circuit</th><th>Étape</th><th>Acteur</th><th>Objet</th><th>Observation</th></tr></thead>
            <tbody>
            @forelse ($journaux as $j)
                <tr>
                    <td class="small">{{ $j->created_at?->format('d/m/Y H:i') }}</td>
                    <td><span class="badge bg-dark">{{ $j->action }}</span></td>
                    <td><code>{{ $j->workflow_code }}</code></td>
                    <td>{{ $j->etape_ordre ?? '—' }}</td>
                    <td class="small">{{ $j->acteur?->name ?? 'Système' }}</td>
                    <td class="small">{{ $j->objet_type ? class_basename($j->objet_type).' #'.$j->objet_id : '—' }}</td>
                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit($j->observation ?? '', 60) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Aucune entrée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $journaux->links() }}</div>
@endsection
