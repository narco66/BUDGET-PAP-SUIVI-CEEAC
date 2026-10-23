@extends('layouts.app')
@section('titre', 'Consolidation des besoins')
@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h5 mb-0">Consolidation des expressions de besoin</h1>
    <a href="{{ route('besoins.index') }}" class="btn btn-outline-secondary btn-sm">Retour aux besoins</a>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach ($exercices as $ex)
                <option value="{{ $ex->annee }}" @selected($ex->id === $exercice->id)>{{ $ex->annee }} — {{ $ex->libelle }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <select name="structure" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Consolidation institutionnelle</option>
            @foreach ($structures as $s)
                <option value="{{ $s->id }}" @selected($structureId === $s->id)>{{ $s->sigle ?? $s->code }} — {{ $s->libelle }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Besoins consolidés</div>
        <div class="fs-4 fw-bold">{{ $synthese['total_besoins'] }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Montant total</div>
        <div class="fs-5 fw-bold">{{ number_format($synthese['montant_total'], 0, ',', ' ') }} XAF</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Doublons détectés</div>
        <div class="fs-4 fw-bold text-warning">{{ $synthese['doublons_count'] }}</div>
    </div></div></div>
    <div class="col-md-3 d-flex align-items-stretch">
        <form method="post" action="{{ route('besoins.consolidation.store') }}" class="w-100">
            @csrf
            <input type="hidden" name="exercice_id" value="{{ $exercice->id }}">
            @if ($structureId)<input type="hidden" name="structure_id" value="{{ $structureId }}">@endif
            <button class="btn btn-primary w-100 h-100">Enregistrer la consolidation</button>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Par structure</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Structure</th><th class="text-end">Besoins</th><th class="text-end">Montant</th></tr></thead>
                    <tbody>
                    @forelse ($synthese['par_structure'] as $ligne)
                        <tr>
                            <td>{{ $ligne['structure'] }}</td>
                            <td class="text-end">{{ $ligne['count'] }}</td>
                            <td class="text-end">{{ number_format($ligne['montant'], 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted text-center py-3">Aucun besoin éligible.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Doublons potentiels</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Original</th><th>Doublon</th><th>Objet</th><th class="text-end">Montant</th></tr></thead>
                    <tbody>
                    @forelse ($synthese['doublons'] as $d)
                        <tr>
                            <td><code>{{ $d['original'] }}</code></td>
                            <td><code>{{ $d['doublon'] }}</code></td>
                            <td>{{ \Illuminate\Support\Str::limit($d['objet'], 40) }}</td>
                            <td class="text-end">{{ number_format($d['montant'], 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted text-center py-3">Aucun doublon détecté.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if ($historique->isNotEmpty())
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-bold">Historique des consolidations</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Référence</th><th>Besoins</th><th>Doublons</th><th class="text-end">Montant</th><th>Par</th><th>Date</th></tr></thead>
            <tbody>
            @foreach ($historique as $h)
                <tr>
                    <td><code>{{ $h->reference }}</code></td>
                    <td>{{ $h->besoins_count }}</td>
                    <td>{{ $h->doublons_elimines }}</td>
                    <td class="text-end">{{ number_format($h->montant_total, 0, ',', ' ') }}</td>
                    <td>{{ $h->auteur?->name ?? '—' }}</td>
                    <td>{{ $h->created_at?->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
