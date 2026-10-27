@extends('layouts.app')
@section('titre', 'Journal de sécurité')

@section('contenu')
@use('App\Support\Horodatage')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-person-lock me-2"></i>Journal de sécurité</h4>
        <span class="text-muted small">Connexions, refus d'accès, mots de passe (événement seul), habilitations et délégations · heures à {{ Horodatage::fuseau() }}</span>
    </div>
    <form method="GET" class="d-flex gap-2"><input type="text" name="acteur" value="{{ $filtres['acteur'] ?? '' }}" class="form-control form-control-sm" placeholder="Acteur ou e-mail"><button class="btn btn-sm btn-outline-secondary">Filtrer</button></form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Échecs de connexion (24 h)</div><div class="fs-4 fw-bold {{ $echecs24h ? 'text-danger' : '' }}">{{ $echecs24h }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Accès refusés (24 h)</div><div class="fs-4 fw-bold {{ $refus24h ? 'text-warning' : '' }}">{{ $refus24h }}</div></div></div></div>
</div>

<h6 class="fw-bold">Événements de sécurité</h6>
@include('audit._table', ['journaux' => $journaux])

<h6 class="fw-bold mt-4">Tentatives de connexion</h6>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Horodatage</th><th>Identifiant</th><th>Résultat</th><th>Motif</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($connexions as $l)
            <tr @class(['table-danger' => ! $l->succes])>
                <td class="small text-nowrap">{{ Horodatage::local($l->created_at) }}</td>
                <td class="small">{{ $l->email }}</td>
                <td><span class="badge text-bg-{{ $l->succes ? 'success' : 'danger' }}">{{ $l->succes ? 'Succès' : 'Échec' }}</span></td>
                <td class="small">{{ $l->motif ?? '—' }}</td>
                <td class="small text-muted">{{ $l->ip }}</td>
            </tr>
        @empty <tr><td colspan="5" class="text-center text-muted py-3">Aucune tentative.</td></tr> @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $connexions->links() }}</div>
@endsection
