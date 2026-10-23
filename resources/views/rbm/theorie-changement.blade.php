@extends('layouts.app')
@section('titre', 'Théorie du changement '.$exercice->annee)

@section('contenu')
@include('rbm._nav-module', ['active' => 'theorie', 'exercice' => $exercice])

@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2"></i>Théorie du changement</h4>
        <span class="text-muted small">Logique causale GAR — Impact → Effets → Produits → Activités · Exercice {{ $exercice->annee }}</span>
    </div>
    @can('rbm.generer_pdf')
    <div class="btn-group btn-group-sm">
        <a href="{{ route('rbm.pdf.theorie-changement', $exercice) }}" class="btn btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>PDF</a>
        <a href="{{ route('rbm.pdf.archiver', [$exercice, 'theorie-changement']) }}" class="btn btn-outline-dark" title="Archiver une version officielle traçable"><i class="bi bi-shield-check me-1"></i>Archiver</a>
    </div>
    @endcan
</div>

@forelse($tc['piliers'] as $pilier)
<div class="card border-0 shadow-sm mb-4">
    {{-- IMPACT --}}
    <div class="card-header text-white fw-semibold" style="background:#0a4a8f">
        <span class="badge bg-light text-dark me-2">IMPACT</span>
        <code class="text-white">{{ $pilier['code'] }}</code> · {{ $pilier['intitule'] }}
    </div>
    <div class="card-body">
        @if($pilier['description'])<p class="text-muted small">{{ $pilier['description'] }}</p>@endif
        @if(!empty($pilier['indicateurs']))
            <div class="mb-3 small">
                <span class="fw-semibold text-primary">Indicateurs d’impact :</span>
                @foreach($pilier['indicateurs'] as $iov)
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1"><code>{{ $iov['code'] }}</code> {{ \Illuminate\Support\Str::limit($iov['libelle'], 40) }} @if($iov['valeur_cible'] !== null)→ cible {{ $iov['valeur_cible'] }}@endif</span>
                @endforeach
            </div>
        @endif

        {{-- EFFETS (axes) --}}
        @forelse($pilier['axes'] as $axe)
        <div class="border-start border-3 border-info ps-3 mb-3">
            <div class="fw-semibold"><span class="badge bg-info text-dark me-2">EFFET</span><code>{{ $axe['code'] }}</code> · {{ $axe['intitule'] }}</div>
            @if(!empty($axe['indicateurs']))
                <div class="small mt-1">
                    <span class="text-info-emphasis">Indicateurs de résultat :</span>
                    @foreach($axe['indicateurs'] as $iov)
                        <span class="badge bg-info-subtle text-info-emphasis border me-1"><code>{{ $iov['code'] }}</code> @if($iov['valeur_cible'] !== null)cible {{ $iov['valeur_cible'] }}@endif</span>
                    @endforeach
                </div>
            @endif

            {{-- PRODUITS --}}
            @forelse($axe['produits'] as $produit)
            <div class="ms-3 mt-2">
                <div class="small"><span class="badge bg-success-subtle text-success-emphasis border me-1">PRODUIT</span><code>{{ $produit['code'] }}</code> · {{ $produit['intitule'] }}</div>
                {{-- ACTIVITÉS --}}
                @if(!empty($produit['activites']))
                <ul class="list-unstyled ms-4 mb-1 small text-muted">
                    @foreach($produit['activites'] as $act)
                        <li><i class="bi bi-arrow-return-right me-1"></i><span class="badge bg-light text-secondary border me-1">ACTIVITÉ</span><code>{{ $act['code'] }}</code> {{ \Illuminate\Support\Str::limit($act['intitule'], 60) }} <span class="text-nowrap">({{ $fmt($act['montant']) }} XAF)</span></li>
                    @endforeach
                </ul>
                @endif
            </div>
            @empty
            <div class="ms-3 small text-muted fst-italic">Aucun produit rattaché.</div>
            @endforelse
        </div>
        @empty
        <div class="small text-muted fst-italic">Aucun effet (axe) défini.</div>
        @endforelse
    </div>
</div>
@empty
<div class="alert alert-info border-0 shadow-sm">Aucun pilier RBM défini pour cet exercice : la chaîne causale ne peut pas encore être représentée.</div>
@endforelse

{{-- HYPOTHÈSES & RISQUES CRITIQUES --}}
<div class="card border-0 shadow-sm border-start border-4 border-warning">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Hypothèses & conditions critiques (risques du PAP)</div>
    <div class="card-body">
        @if(!empty($tc['hypotheses']))
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Hypothèse / risque</th><th>Probabilité</th><th>Impact</th><th>Mesure d’atténuation</th></tr></thead>
                <tbody>
                @foreach($tc['hypotheses'] as $h)
                    <tr>
                        <td><code>{{ $h['code'] }}</code></td>
                        <td>{{ $h['libelle'] }}</td>
                        <td>{{ ucfirst((string) $h['probabilite']) }}</td>
                        <td>{{ ucfirst((string) $h['impact']) }}</td>
                        <td class="small text-muted">{{ $h['mitigation'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-muted mb-0">Aucun risque/hypothèse enregistré sur le PAP de cet exercice. La réalisation de l’impact suppose la tenue des conditions sectorielles habituelles.</p>
        @endif
    </div>
</div>
@endsection
