@extends('layouts.app')
@section('titre', 'Matrice RBM · '.$exercice->annee)
@php
$fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
$filtresActifs = collect($filtres)->filter(fn ($v) => $v !== '')->isNotEmpty();
$taux = fn ($v) => $v === null ? '—' : number_format($v, 0).' %';
$peutVoirIov = auth()->user()->can('indicateur.voir');
$couleurTaux = fn ($v) => $v === null ? 'text-muted' : ($v >= 80 ? 'text-success' : ($v >= 40 ? 'text-warning' : 'text-danger'));
@endphp

@push('styles')
<style>
    .matrice-rbm thead th { position: sticky; top: 0; z-index: 2; }
    .matrice-rbm .ligne-pilier td { background: #e8f0fa; font-weight: 600; }
    .matrice-rbm .ligne-axe td { background: #f6f8fb; }
    .matrice-rbm .montant { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .matrice-rbm .chemin code { font-size: .75rem; }
</style>
@endpush

@section('contenu')
@include('rbm._nav-module', ['exercice' => $exercice, 'page' => 'matrice'])

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-grid-3x3 me-1"></i>Matrice RBM / GAR · {{ $exercice->annee }}</h4>
        <span class="text-muted small">Pilier → Axe → Produit → Sous-produit → Activité, avec tâches, indicateurs (IOV), financement et réalisation.</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('rbm.export.matrice', $exercice) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        <a href="{{ route('rbm.pdf.matrice', $exercice) }}" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    </div>
</div>

{{-- Indicateurs --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted">Activités</div><div class="fs-5 fw-bold">{{ $indicateurs['activites'] }}</div>
        @if($filtresActifs)<div class="small text-muted">sur {{ $total }}</div>@endif
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted">Montant programmé</div><div class="fs-5 fw-bold">{{ $fmt($indicateurs['montant']) }}</div>
        <div class="small text-muted">CEEAC {{ $fmt($indicateurs['ceeac']) }} · PTF {{ $fmt($indicateurs['ptf']) }}</div>
    </div></div></div>
    <div class="col-6 col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted">Tâches</div><div class="fs-5 fw-bold">{{ $indicateurs['taches'] }}</div>
    </div></div></div>
    <div class="col-6 col-md-2">
        <a href="{{ route('rbm.matrice', array_merge(['exercice' => $exercice->id], $filtres, ['couverture' => 'sans_tache'])) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-reset @if($filtres['couverture'] === 'sans_tache') border-start border-4 border-warning @endif"><div class="card-body py-2">
            <div class="small text-muted"><i class="bi bi-exclamation-triangle text-warning me-1"></i>Sans tâche</div><div class="fs-5 fw-bold">{{ $indicateurs['sans_tache'] }}</div>
        </div></a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('rbm.matrice', array_merge(['exercice' => $exercice->id], $filtres, ['couverture' => 'sans_iov'])) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-reset @if($filtres['couverture'] === 'sans_iov') border-start border-4 border-danger @endif"><div class="card-body py-2">
            <div class="small text-muted"><i class="bi bi-graph-down text-danger me-1"></i>Sans indicateur (IOV)</div>
            <div class="fs-5 fw-bold">{{ $indicateurs['sans_iov'] }} <span class="fs-6 fw-normal text-muted">· couverture {{ $indicateurs['couverture_iov'] }} %</span></div>
        </div></a>
    </div>
</div>

{{-- Filtres --}}
<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-0">Recherche</label>
                <input type="search" name="q" value="{{ $filtres['q'] }}" class="form-control form-control-sm" placeholder="Code ou intitulé d'activité, produit, sous-produit">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">Pilier</label>
                <select name="pilier" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach($piliers as $code => $intitule)<option value="{{ $code }}" @selected($filtres['pilier'] === (string) $code)>{{ $code }} · {{ \Illuminate\Support\Str::limit($intitule, 40) }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Structure</label>
                <select name="structure" class="form-select form-select-sm">
                    <option value="">Toutes</option>
                    @foreach($structures as $code => $libelle)<option value="{{ $code }}" @selected($filtres['structure'] === (string) $code)>{{ $code }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Couverture</label>
                <select name="couverture" class="form-select form-select-sm">
                    <option value="">Toutes</option>
                    <option value="sans_tache" @selected($filtres['couverture'] === 'sans_tache')>Sans tâche</option>
                    <option value="sans_iov" @selected($filtres['couverture'] === 'sans_iov')>Sans indicateur</option>
                    <option value="sans_montant" @selected($filtres['couverture'] === 'sans_montant')>Sans montant</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-fill" title="Filtrer"><i class="bi bi-funnel"></i></button>
                @if($filtresActifs)<a href="{{ route('rbm.matrice', $exercice) }}" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>@endif
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive" style="max-height: 75vh">
        <table class="table table-sm table-hover align-middle mb-0 matrice-rbm">
            <thead class="table-light small">
                <tr>
                    <th>Chaîne</th>
                    <th>Activité</th>
                    <th>Structure</th>
                    <th class="text-end">Montant</th>
                    <th class="text-end">dont PTF</th>
                    <th class="text-center">Tâches</th>
                    <th class="text-center">IOV</th>
                    <th class="text-center" title="Réalisation physique / financière">Réal. phys. / fin.</th>
                </tr>
            </thead>
            <tbody>
            @forelse($parPilier as $codePilier => $lignesPilier)
                <tr class="ligne-pilier">
                    <td colspan="3"><span class="badge bg-primary me-1">PILIER</span><code>{{ $codePilier }}</code> {{ $lignesPilier->first()['pilier_intitule'] }}</td>
                    <td class="text-end montant">{{ $fmt($lignesPilier->sum('montant')) }}</td>
                    <td class="text-end montant">{{ $fmt($lignesPilier->sum('montant_ptf')) }}</td>
                    <td class="text-center">{{ $lignesPilier->sum('nb_taches') }}</td>
                    <td class="text-center">{{ $lignesPilier->sum('nb_indicateurs') }}</td>
                    <td class="text-center small">{{ $lignesPilier->count() }} act.</td>
                </tr>
                @foreach($lignesPilier->groupBy('axe') as $codeAxe => $lignesAxe)
                    <tr class="ligne-axe small">
                        <td colspan="3" class="ps-3"><span class="badge bg-success-subtle text-success-emphasis me-1">AXE</span><code>{{ $codeAxe }}</code> {{ \Illuminate\Support\Str::limit($lignesAxe->first()['axe_intitule'], 90) }}</td>
                        <td class="text-end montant">{{ $fmt($lignesAxe->sum('montant')) }}</td>
                        <td class="text-end montant">{{ $fmt($lignesAxe->sum('montant_ptf')) }}</td>
                        <td class="text-center">{{ $lignesAxe->sum('nb_taches') }}</td>
                        <td class="text-center">{{ $lignesAxe->sum('nb_indicateurs') }}</td>
                        <td></td>
                    </tr>
                    @foreach($lignesAxe as $l)
{{-- Ligne compacte (215 lignes : l'indentation Blade doublait le poids de la page). --}}
<tr><td class="chemin small text-nowrap ps-4" title="{{ $l['sous_produit_intitule'] }}"><code>{{ $l['produit'] }}</code> › <code>{{ $l['sous_produit'] }}</code></td><td class="small"><code>{{ $l['activite'] }}</code> {{ $l['intitule'] }}</td><td class="small">{{ $l['structure'] ?? '—' }}</td><td class="text-end montant small">{{ $fmt($l['montant']) }}</td><td class="text-end montant small text-muted">{{ $l['montant_ptf'] > 0 ? $fmt($l['montant_ptf']) : '—' }}</td><td class="text-center">@if($l['nb_taches'] > 0){{ $l['nb_taches'] }}@else<span class="badge bg-warning-subtle text-warning-emphasis">0</span>@endif</td><td class="text-center">@if($l['nb_indicateurs'] === 0)<span class="badge bg-danger-subtle text-danger-emphasis">0</span>@elseif($peutVoirIov)<a href="{{ route('rbm.indicateurs.index', ['activite', $l['activite_id']]) }}?exercice={{ $exercice->id }}" class="text-decoration-none">{{ $l['nb_indicateurs'] }}</a>@else{{ $l['nb_indicateurs'] }}@endif</td><td class="text-center small text-nowrap"><span class="{{ $couleurTaux($l['taux_physique']) }}">{{ $taux($l['taux_physique']) }}</span> / <span class="{{ $couleurTaux($l['taux_financier']) }}">{{ $taux($l['taux_financier']) }}</span></td></tr>
                    @endforeach
                @endforeach
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">
                    @if($filtresActifs)
                        Aucune activité ne correspond à ces filtres. <a href="{{ route('rbm.matrice', $exercice) }}">Réinitialiser</a>
                    @else
                        Aucune donnée pour cet exercice.
                    @endif
                </td></tr>
            @endforelse
            </tbody>
            @if($lignes->isNotEmpty())
            <tfoot class="table-light small fw-semibold">
                <tr>
                    <td colspan="3">Total {{ $filtresActifs ? 'filtré' : 'exercice' }} ({{ $indicateurs['activites'] }} activités)</td>
                    <td class="text-end montant">{{ $fmt($indicateurs['montant']) }}</td>
                    <td class="text-end montant">{{ $fmt($indicateurs['ptf']) }}</td>
                    <td class="text-center">{{ $indicateurs['taches'] }}</td>
                    <td class="text-center">{{ $lignes->sum('nb_indicateurs') }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
