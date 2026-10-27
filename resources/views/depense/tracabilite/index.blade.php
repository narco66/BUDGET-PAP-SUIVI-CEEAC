@extends('layouts.app')
@section('titre', 'Traçabilité des dossiers')
@section('contenu')
<div class="mb-3">
    <h4 class="fw-bold mb-1"><i class="bi bi-diagram-3 me-1"></i>Traçabilité et suivi des dossiers</h4>
    <span class="text-muted">Chaîne de dépense EB → ENG → LIQ → ORD → PAY : origine, position actuelle, acteurs, délais et blocages.</span>
</div>

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex gap-2 flex-wrap">
        <input type="search" name="q" value="{{ $q }}" class="form-control form-control-lg flex-grow-1" style="min-width:260px" autofocus
               placeholder="EB-2026-0040, ENG-…, LIQ-…, ORD-…, PAY-…, bénéficiaire, référence bancaire, montant">
        <button class="btn btn-primary btn-lg"><i class="bi bi-search me-1"></i>Rechercher</button>
    </div>
</form>

<div class="row g-3 mb-3">
    @foreach([
        ['en_attente', 'Étapes en attente', 'hourglass-split', 'primary'],
        ['en_retard', 'En retard', 'alarm', 'danger'],
        ['non_prises', 'Non prises en charge', 'person-dash', 'warning'],
        ['affectation_defaut', 'Affectations par défaut', 'person-exclamation', 'secondary'],
        ['a_corriger', 'Retournés pour correction', 'arrow-counterclockwise', 'info'],
    ] as [$cle, $libelle, $icone, $couleur])
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small"><i class="bi bi-{{ $icone }} text-{{ $couleur }} me-1"></i>{{ $libelle }}</div>
                <div class="fs-3 fw-bold">{{ number_format($indicateurs[$cle], 0, ',', ' ') }}</div>
            </div></div>
        </div>
    @endforeach
</div>

@if($q !== '')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold">{{ $resultats->count() }} résultat(s) pour « {{ $q }} »</div>
    @if($resultats->isEmpty())
        <div class="card-body text-muted">Aucun dossier de votre périmètre ne correspond à cette recherche.</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Dossier</th><th>Objet / bénéficiaire</th><th class="text-end">Montant (XAF)</th><th>Statut</th><th>Trouvé par</th><th></th></tr></thead>
            <tbody>
            @foreach($resultats as $r)
                @php $o = $r['objet']; @endphp
                <tr>
                    <td><code>{{ $o->numero }}</code></td>
                    <td>{{ \Illuminate\Support\Str::limit($o->objet ?? $o->beneficiaire ?? $o->reference_bancaire ?? '—', 60) }}</td>
                    <td class="text-end">{{ number_format((float) ($o->montant_total ?? $o->montant_xaf ?? $o->montant ?? $o->montant_estime ?? 0), 0, ',', ' ') }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $o->statut }}</span></td>
                    <td class="small text-muted">{{ $r['motif'] }}</td>
                    <td class="text-end"><a href="{{ route('depense.tracabilite.show', [$r['type'], $o->getKey()]) }}" class="btn btn-sm btn-outline-primary">Tracer</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endif
@endsection
