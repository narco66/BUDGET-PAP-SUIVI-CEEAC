@extends('layouts.app')
@section('titre', 'Planification Reporting')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('reporting.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Reporting & Analyse</a>
        <h4 class="fw-bold mb-0">Planification & audit</h4>
        <span class="text-muted small">Scheduler Laravel · génération automatique · journal des consultations</span>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Planification automatique</div>
            <div class="card-body">
                <p class="small text-muted">Les tâches planifiées s'exécutent via <code>php artisan schedule:run</code> (cron système).</p>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Trimestriel (T auto)</span>
                        @if ($schedule['enabled'] && ($schedule['trimestriel'] ?? false))
                            <span class="badge bg-success">Actif — 1er jour T1–T4, 06h00</span>
                        @else
                            <span class="badge bg-secondary">Inactif</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Semestriel</span>
                        @if ($schedule['enabled'] && ($schedule['semestriel'] ?? false))
                            <span class="badge bg-success">Actif — 1er janv./juil., 06h00</span>
                        @else
                            <span class="badge bg-secondary">Inactif</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Annuel</span>
                        @if ($schedule['enabled'] && ($schedule['annuel'] ?? false))
                            <span class="badge bg-success">Actif — 1er janvier, 06h00</span>
                        @else
                            <span class="badge bg-secondary">Inactif</span>
                        @endif
                    </li>
                </ul>
                <div class="mt-3 small text-muted">
                    Variables : <code>REPORTING_SCHEDULE_ENABLED</code>, <code>REPORTING_SCHEDULE_TRIMESTRIEL</code>, etc.
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Dernières générations périodiques / IPSAS</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Réf.</th><th>Type</th><th>Format</th><th>Le</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($derniersPeriodiques as $r)
                        <tr>
                            <td class="font-monospace small">{{ $r->reference }}</td>
                            <td>{{ \App\Services\Reporting\RapportComposer::TYPES[$r->type] ?? $r->type }}</td>
                            <td>{{ strtoupper($r->format) }}</td>
                            <td class="small">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                            <td><a href="{{ route('reporting.telecharger', $r) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-download"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center py-3">Aucune génération planifiée enregistrée.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Journal audit reporting</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Action</th><th>Rapport</th><th>Utilisateur</th><th>IP</th><th>Le</th></tr></thead>
                    <tbody>
                    @forelse ($auditRecents as $a)
                        <tr>
                            <td><span class="badge bg-light text-dark">{{ $a->action }}</span></td>
                            <td class="small font-monospace">{{ $a->rapport?->reference ?? ($a->type_rapport ?? '—') }}</td>
                            <td class="small">{{ $a->user?->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $a->ip ?? '—' }}</td>
                            <td class="small">{{ $a->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center py-3">Aucune entrée audit.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
