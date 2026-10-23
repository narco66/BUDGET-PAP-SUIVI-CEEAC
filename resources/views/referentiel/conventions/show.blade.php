@extends('layouts.app')
@section('titre', 'Convention '.$convention->code)

@php $fmt = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('partenaires.show', $convention->partenaire_id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Partenaire</a>
    <div class="d-flex gap-2">
        @can('convention_financement.modifier')
            <a href="{{ route('conventions.edit', $convention) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#decModal"><i class="bi bi-cash-stack me-1"></i>Décaissement</button>
        @endcan
    </div>
</div>

@if (session('erreur'))<div class="alert alert-danger">{{ session('erreur') }}</div>@endif

<div class="row g-3 mb-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h5 class="mb-0"><code>{{ $convention->code }}</code> — {{ $convention->libelle }}</h5>
            <span class="badge bg-primary">{{ \App\Models\ConventionFinancement::STATUTS[$convention->statut] ?? $convention->statut }}</span>
        </div>
        <dl class="row mb-0">
            <dt class="col-sm-4 text-muted">Partenaire</dt><dd class="col-sm-8">{{ $convention->partenaire?->sigle ?? $convention->partenaire?->libelle }}</dd>
            <dt class="col-sm-4 text-muted">Source</dt><dd class="col-sm-8">{{ $convention->sourceFinancement?->libelle ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Réf. accord</dt><dd class="col-sm-8">{{ $convention->reference_accord ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Période</dt><dd class="col-sm-8">{{ $convention->date_debut?->format('d/m/Y') ?? '—' }} → {{ $convention->date_fin?->format('d/m/Y') ?? '—' }}</dd>
        </dl>
    </div></div></div>
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="d-flex justify-content-between"><span class="text-muted small text-uppercase">Accord</span><strong>{{ $fmt($convention->montant_accord) }} XAF</strong></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-muted small text-uppercase">Décaissé ({{ $convention->taux_mobilisation }}%)</span><strong class="text-success">{{ $fmt($convention->montant_decaisse) }} XAF</strong></div>
        <div class="progress my-2" style="height:6px;"><div class="progress-bar bg-success" style="width: {{ min($convention->taux_mobilisation, 100) }}%"></div></div>
        <div class="d-flex justify-content-between"><span class="text-muted small text-uppercase">Reliquat</span><strong class="{{ $convention->reliquat > 0 ? 'text-danger' : 'text-muted' }}">{{ $fmt($convention->reliquat) }} XAF</strong></div>
    </div></div></div>
</div>

@if ($parActivite->isNotEmpty())
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h6 class="text-muted text-uppercase small mb-3"><i class="bi bi-diagram-3 me-1"></i>Financement par activité du PAP</h6>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Activité</th><th class="text-end">Décaissé (XAF)</th></tr></thead>
        <tbody>
        @foreach ($parActivite as $row)
            <tr>
                <td class="small">{{ $row->activite ? $row->activite->code.' — '.$row->activite->intitule : 'Non rattaché' }}</td>
                <td class="text-end text-success">{{ $fmt($row->montant) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div></div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold"><i class="bi bi-cash-stack me-1"></i>Décaissements ({{ $convention->decaissements->count() }})</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Référence</th><th>Date</th><th>Objet</th><th>Activité</th><th class="text-end">Montant</th><th class="text-end">XAF</th><th class="text-center">Statut</th><th></th></tr></thead>
        <tbody>
        @forelse ($convention->decaissements as $d)
            <tr class="{{ $d->statut === 'annule' ? 'text-muted' : '' }}">
                <td><code>{{ $d->reference }}</code></td>
                <td class="small">{{ $d->date_decaissement?->format('d/m/Y') }}</td>
                <td class="small">{{ $d->objet }}</td>
                <td class="small">{{ $d->activite?->code ?? '—' }}</td>
                <td class="text-end">{{ $fmt($d->montant) }} {{ $d->devise }}</td>
                <td class="text-end">{{ $fmt($d->montant_xaf) }}</td>
                <td class="text-center"><span class="badge bg-light text-dark border">{{ \App\Models\DecaissementConvention::STATUTS[$d->statut] ?? $d->statut }}</span></td>
                <td class="text-end">
                    @if ($d->statut !== 'annule')
                        @can('convention_financement.modifier')
                        <form method="POST" action="{{ route('decaissements.annuler', $d) }}" onsubmit="return confirm('Annuler ce décaissement ?')">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button></form>
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-3">Aucun décaissement.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

@can('convention_financement.modifier')
<div class="modal fade" id="decModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" action="{{ route('decaissements.store', $convention) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Nouveau décaissement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Reliquat disponible : <strong>{{ $fmt($convention->reliquat) }} XAF</strong></p>
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label">Référence <span class="text-danger">*</span></label><input type="text" name="reference" maxlength="60" class="form-control" required></div>
                    <div class="col-md-8"><label class="form-label">Objet <span class="text-danger">*</span></label><input type="text" name="objet" maxlength="255" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label">Montant <span class="text-danger">*</span></label><input type="number" step="0.01" min="0.01" name="montant" class="form-control" required></div>
                    <div class="col-md-2"><label class="form-label">Devise <span class="text-danger">*</span></label><input type="text" name="devise" value="XAF" maxlength="3" class="form-control text-uppercase" required></div>
                    <div class="col-md-3"><label class="form-label">Taux <span class="text-danger">*</span></label><input type="number" step="0.000001" min="0.000001" name="taux_change" value="1" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" name="date_decaissement" value="{{ now()->toDateString() }}" class="form-control" required></div>
                    <div class="col-md-8"><label class="form-label">Activité financée (PAP)</label>
                        <select name="activite_id" class="form-select">
                            <option value="">— Non rattaché</option>
                            @foreach ($activites as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ \Illuminate\Support\Str::limit($a->intitule, 50) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Statut <span class="text-danger">*</span></label>
                        <select name="statut" class="form-select" required>
                            @foreach (\App\Models\DecaissementConvention::STATUTS as $k => $v)<option value="{{ $k }}" @selected($k==='execute')>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Observations</label><textarea name="observations" rows="2" class="form-control"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-success">Enregistrer</button></div>
        </form>
    </div>
</div>
@endcan
@endsection
