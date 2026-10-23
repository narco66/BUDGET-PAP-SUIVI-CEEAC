@extends('layouts.app')
@section('titre', $titre)
@php use App\Services\Reporting\Fmt; $f = fn ($v, $fmt) => Fmt::valeur($v, $fmt); @endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('reporting.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Reporting & Analyse</a>
        <h4 class="fw-bold mb-0">{{ $titre }}</h4>
        <span class="text-muted small">Exercice <span class="badge bg-primary">{{ $exercice->annee }}</span>
            · Période <span class="badge bg-secondary">{{ $periodeCourante }}</span>
            · données réelles consolidées</span>
    </div>
</div>

{{-- Filtres + génération --}}
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label small mb-0">Exercice</label>
            <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice->id === $ex->id)>{{ $ex->annee }}</option>@endforeach
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">Période</label>
            <select name="periode" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($periodeOptions as $val => $lib)
                    <option value="{{ $val }}" @selected($periodeCourante === $val)>{{ $lib }}</option>
                @endforeach
            </select>
        </div>
        @if ($type === 'pap')
            <div class="col-auto">
                <label class="form-label small mb-0">Département (optionnel)</label>
                <select name="structure_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Tous les départements</option>
                    @foreach ($structures as $s)<option value="{{ $s->id }}" @selected(request('structure_id') == $s->id)>{{ $s->sigle ?? $s->libelle }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="col"></div>
        @can('generate', App\Models\Rapport::class)
        @php
            $q = array_filter(['exercice' => $exercice->id, 'structure_id' => request('structure_id'), 'periode' => $periodeCourante]);
            $asyncType = \App\Services\Reporting\ReportingAsyncPolicy::doitAsync($type, 'pdf');
        @endphp
        <div class="col-auto">
            <label class="form-label small mb-0 d-block">Générer</label>
            @if ($asyncType)
                <span class="badge bg-light text-dark border mb-1"><i class="bi bi-hourglass-split me-1"></i>Rendu lourd — génération en arrière-plan</span>
            @endif
            <div class="btn-group btn-group-sm" role="group">
                <a class="btn btn-danger" href="{{ route('reporting.generer', array_merge(['type' => $type, 'format' => 'pdf'], $q)) }}"><i class="bi bi-file-pdf me-1"></i>PDF</a>
                <a class="btn btn-success" href="{{ route('reporting.generer', array_merge(['type' => $type, 'format' => 'excel'], $q)) }}"><i class="bi bi-file-excel me-1"></i>Excel</a>
                <a class="btn btn-primary" href="{{ route('reporting.generer', array_merge(['type' => $type, 'format' => 'word'], $q)) }}"><i class="bi bi-file-word me-1"></i>Word</a>
                <a class="btn btn-outline-secondary" href="{{ route('reporting.generer', array_merge(['type' => $type, 'format' => 'csv'], $q)) }}"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                <a class="btn btn-warning" href="{{ route('reporting.generer', array_merge(['type' => $type, 'format' => 'pptx'], $q)) }}"><i class="bi bi-file-earmark-slides me-1"></i>PowerPoint</a>
            </div>
            <a class="btn btn-outline-dark btn-sm ms-2" href="{{ route('reporting.historique') }}"><i class="bi bi-archive me-1"></i>Centre d'exports</a>
        </div>
        @endcan
    </form>
</div></div>

{{-- Aperçu (mêmes données que les exports) --}}
@foreach ($apercu['sections'] as $i => $s)
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h6 class="fw-bold text-primary">{{ $i + 1 }}. {{ $s['titre'] ?? '' }}</h6>

        @if ($s['type'] === 'kpi')
            <div class="row g-2">
                @foreach ($s['items'] as $it)
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2">
                            <div class="text-muted small">{{ $it['label'] }}</div>
                            <div class="fs-5 fw-bold text-primary">{{ $f($it['valeur'], $it['fmt'] ?? 'text') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($s['type'] === 'table')
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                        @foreach ($s['colonnes'] as $col)<th class="{{ ($col['align'] ?? 'left') === 'right' ? 'text-end' : '' }}">{{ $col['label'] }}</th>@endforeach
                    </tr></thead>
                    <tbody>
                        @foreach ($s['lignes'] as $ri => $ligne)
                            <tr @if(isset($s['niveaux']) && $s['niveaux'][$ri] === 0) class="table-primary fw-bold" @elseif(isset($s['niveaux']) && $s['niveaux'][$ri] === 1) class="fw-semibold" @endif>
                                @foreach ($ligne as $ci => $val)
                                    <td class="{{ ($s['colonnes'][$ci]['align'] ?? 'left') === 'right' ? 'text-end' : '' }}"
                                        @if($ci === 0 && isset($s['niveaux'])) style="padding-left:{{ 8 + $s['niveaux'][$ri] * 16 }}px" @endif>
                                        {{ $f($val, $s['colonnes'][$ci]['fmt'] ?? 'text') }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        @if (! empty($s['total']))
                            <tr class="table-secondary fw-bold">
                                @foreach ($s['total'] as $ci => $val)<td class="{{ ($s['colonnes'][$ci]['align'] ?? 'left') === 'right' ? 'text-end' : '' }}">{{ $f($val, $s['colonnes'][$ci]['fmt'] ?? 'text') }}</td>@endforeach
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @elseif ($s['type'] === 'liste')
            <ul class="mb-0">@foreach ($s['items'] as $it)<li>{{ $it }}</li>@endforeach</ul>
        @else
            <p class="mb-0 text-justify">{{ $s['contenu'] ?? '' }}</p>
        @endif
    </div></div>
@endforeach
@endsection
