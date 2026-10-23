@extends('layouts.app')
@section('titre', $modele->titre)
@php use App\Services\Reporting\Fmt; $f = fn ($v, $fmt) => Fmt::valeur($v, $fmt); @endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('reporting.modeles.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Rapports personnalisés</a>
        <h4 class="fw-bold mb-0">{{ $modele->titre }}</h4>
        <span class="text-muted small">{{ $modele->code }} · {{ $sources[$modele->source]['label'] ?? $modele->source }}</span>
    </div>
    @can('generate', App\Models\Rapport::class)
        <div class="btn-group btn-group-sm">
            <a class="btn btn-danger" href="{{ route('reporting.modeles.generer', ['rapportPersonnalise' => $modele, 'format' => 'pdf', 'exercice' => $exercice->id]) }}"><i class="bi bi-file-pdf me-1"></i>PDF</a>
            <a class="btn btn-success" href="{{ route('reporting.modeles.generer', ['rapportPersonnalise' => $modele, 'format' => 'excel', 'exercice' => $exercice->id]) }}"><i class="bi bi-file-excel me-1"></i>Excel</a>
            <a class="btn btn-outline-secondary" href="{{ route('reporting.modeles.generer', ['rapportPersonnalise' => $modele, 'format' => 'csv', 'exercice' => $exercice->id]) }}"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
        </div>
    @endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach ($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice->id === $ex->id)>{{ $ex->annee }}</option>
            @endforeach
        </select>
    </div>
</form>

@if ($modele->description)
    <div class="alert alert-light border small mb-3">{{ $modele->description }}</div>
@endif

@foreach ($apercu['sections'] as $i => $s)
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h6 class="fw-bold text-primary">{{ $i + 1 }}. {{ $s['titre'] ?? '' }}</h6>
        @if ($s['type'] === 'kpi')
            <div class="row g-2">
                @foreach ($s['items'] as $it)
                    <div class="col-6 col-md-4">
                        <div class="border rounded p-2">
                            <div class="text-muted small">{{ $it['label'] }}</div>
                            <div class="fs-5 fw-bold">{{ $f($it['valeur'], $it['fmt'] ?? 'text') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($s['type'] === 'table')
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead><tr>@foreach ($s['colonnes'] as $c)<th>{{ $c['label'] }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($s['lignes'] as $row)
                        <tr>@foreach ($row as $j => $cell)<td>{{ $f($cell, $s['colonnes'][$j]['fmt'] ?? 'text') }}</td>@endforeach</tr>
                    @endforeach
                    @if (!empty($s['total']))
                        <tr class="table-light fw-bold">@foreach ($s['total'] as $j => $cell)<td>{{ $f($cell, $s['colonnes'][$j]['fmt'] ?? 'text') }}</td>@endforeach</tr>
                    @endif
                    </tbody>
                </table>
            </div>
        @endif
    </div></div>
@endforeach
@endsection
