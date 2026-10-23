@extends('layouts.app')
@section('titre', 'Rapprochement · '.$session->reference)
@section('contenu')
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold">{{ $session->reference }} · {{ $session->intitule }}</h5>
        <span class="text-muted">Exercice {{ $session->exercice?->annee }} · {{ str_replace('_',' ',$session->statut) }}</span>
    </div>
    <div class="d-flex gap-2">
        @if($session->statut !== 'clos')
            <form action="{{ route('depense.rapprochement.auto', $session) }}" method="POST">@csrf<button class="btn btn-sm btn-outline-primary">Rapprochement auto</button></form>
            <form action="{{ route('depense.rapprochement.cloturer', $session) }}" method="POST" onsubmit="return confirm('Clôturer ?')">@csrf<button class="btn btn-sm btn-success">Clôturer</button></form>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Lignes du relevé</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Libellé</th><th>Réf.</th><th class="text-end">Montant</th><th>Statut</th><th>Paiement</th></tr></thead>
                    <tbody>
                    @foreach($session->lignes as $l)
                        <tr>
                            <td>{{ $l->date_operation?->format('d/m/Y') }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($l->libelle,40) }}</td>
                            <td><code class="small">{{ $l->reference_bancaire ?? '—' }}</code></td>
                            <td class="text-end">{{ number_format($l->montant,0,',',' ') }}</td>
                            <td><span class="badge bg-{{ ['non_rapproche'=>'warning','rapproche'=>'success','ecart'=>'danger'][$l->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$l->statut) }}</span></td>
                            <td class="small">{{ $l->paiement?->numero ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        @if($session->statut !== 'clos')
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h6 class="fw-bold">Importer CSV</h6>
            <p class="small text-muted">Format : date;libellé;référence;montant (ou date;libellé;montant)</p>
            <form method="POST" action="{{ route('depense.rapprochement.importer', $session) }}">@csrf
                <textarea name="lignes" class="form-control mb-2" rows="5" placeholder="2026-01-15;Virement fournisseur;REF123;500000"></textarea>
                <button class="btn btn-sm btn-primary w-100">Importer</button>
            </form>
        </div></div>
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h6 class="fw-bold">Rapprochement manuel</h6>
            <form method="POST" action="{{ route('depense.rapprochement.lier', $session) }}">@csrf
                <select name="ligne_id" class="form-select form-select-sm mb-2" required>
                    <option value="">Ligne relevé…</option>
                    @foreach($session->lignes->where('statut','non_rapproche') as $l)
                        <option value="{{ $l->id }}">{{ $l->date_operation?->format('d/m') }} · {{ number_format($l->montant,0,',',' ') }} XAF</option>
                    @endforeach
                </select>
                <select name="paiement_id" class="form-select form-select-sm mb-2" required>
                    <option value="">Paiement…</option>
                    @foreach($paiementsEligibles as $p)
                        <option value="{{ $p->id }}">{{ $p->numero }} · {{ number_format($p->montant,0,',',' ') }} XAF</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary w-100">Lier</button>
            </form>
        </div></div>
        @endif
        <div class="card border-0 shadow-sm mt-3"><div class="card-body py-2">
            <div class="small text-muted">Total relevé</div><div class="fw-bold">{{ number_format($session->montant_releve,0,',',' ') }} XAF</div>
            <div class="small text-muted mt-2">Rapproché</div><div class="fw-bold text-success">{{ number_format($session->montant_rapproche,0,',',' ') }} XAF</div>
        </div></div>
    </div>
</div>
<div class="mt-3"><a href="{{ route('depense.rapprochement.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
@endsection
