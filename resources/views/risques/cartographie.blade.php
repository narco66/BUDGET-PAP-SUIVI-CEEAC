@extends('layouts.app')
@section('titre', 'Cartographie des risques')
@php
    $meta = fn ($n) => \App\Models\Risque::niveauMeta($n);
    $couleurCell = ['critique' => '#dc3545', 'eleve' => '#fd7e14', 'modere' => '#ffc107', 'faible' => '#198754'];
    // Texte foncé sur les fonds clairs (orange, jaune) : contraste WCAG ≥ 4,5:1.
    $texteCell = ['critique' => '#fff', 'eleve' => '#212529', 'modere' => '#212529', 'faible' => '#fff'];
@endphp

@push('styles')<style>
    .heatmap td { width:90px; height:64px; text-align:center; vertical-align:middle; color:#fff; font-weight:600; border:2px solid #fff; }
    .heatmap .axe { background:#fff; color:#333; font-weight:600; }
    .heatmap .cell-n { font-size:1.25rem; } .heatmap .cell-c { font-size:.75rem; }
</style>@endpush

@section('contenu')
<a href="{{ route('risques.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Registre des risques</a>
<h4 class="fw-bold mb-1">Cartographie des risques</h4>
<p class="text-muted small">Matrice de criticité (Probabilité × Impact) — registre central + risques stratégiques & PAP existants.</p>

<div class="row g-3 mb-3">
    @foreach (['critique', 'eleve', 'modere', 'faible'] as $niv)
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 d-flex align-items-center gap-2">
            <span class="badge bg-{{ $meta($niv)['classe'] }}">&nbsp;</span>
            <div><div class="fs-5 fw-bold">{{ $carto['par_niveau'][$niv] ?? 0 }}</div><div class="text-muted small">{{ $meta($niv)['libelle'] }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h6 class="fw-semibold mb-3">Matrice de criticité ({{ $carto['total'] }} risque(s))</h6>
            <table class="heatmap mx-auto">
                <tbody>
                @for ($p = 4; $p >= 1; $p--)
                    <tr>
                        <td class="axe small">P{{ $p }} · {{ \App\Models\Risque::PROBABILITES[$p] }}</td>
                        @for ($i = 1; $i <= 4; $i++)
                            @php $cell = $carto['cellules'][$p][$i]; @endphp
                            <td style="background:{{ $couleurCell[$cell['niveau']] }};color:{{ $texteCell[$cell['niveau']] }}">
                                <div class="cell-n">{{ $cell['nombre'] }}</div>
                                <div class="cell-c">crit. {{ $cell['criticite'] }}</div>
                            </td>
                        @endfor
                    </tr>
                @endfor
                <tr><td class="axe"></td>@for ($i = 1; $i <= 4; $i++)<td class="axe small">I{{ $i }}<br>{{ \App\Models\Risque::IMPACTS[$i] }}</td>@endfor</tr>
                </tbody>
            </table>
            <p class="text-muted small text-center mt-2 mb-0">Lignes = probabilité · Colonnes = impact · Chiffre = nombre de risques</p>
        </div></div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3 me-1"></i>Risques existants agrégés ({{ $existants->count() }})</div>
            <div class="table-responsive" style="max-height:380px;overflow:auto"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Source</th><th>Code</th><th>Libellé</th><th class="text-center">Niveau</th></tr></thead>
                <tbody>
                @forelse ($existants as $e)
                    <tr><td><span class="badge bg-secondary-subtle text-secondary-emphasis text-capitalize">{{ $e->source }}</span></td>
                        <td class="font-monospace small">{{ $e->code }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($e->libelle, 26) }}</td>
                        <td class="text-center"><span class="badge bg-{{ $meta($e->niveau)['classe'] }}">{{ $meta($e->niveau)['libelle'] }}</span></td></tr>
                @empty<tr><td colspan="4" class="text-center text-muted py-3">Aucun risque stratégique/PAP.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
