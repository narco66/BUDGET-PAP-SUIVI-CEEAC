@extends('layouts.app')
@section('titre', 'Sauvegardes & restauration')
@php
    $bs = ['en_cours' => 'info', 'succes' => 'success', 'echec' => 'danger'];
    $rs = fn ($s) => \App\Models\RestoreOperation::statutClasse($s);
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Sauvegardes & restauration</h4>
        <span class="text-muted small">Continuité de service (ISO 22301) — la restauration suit un workflow contrôlé (validation 4 yeux)</span>
    </div>
    @can('admin_technique.backup')
    <form method="POST" action="{{ route('sauvegardes.executer') }}" class="d-flex gap-2">
        @csrf
        <select name="type" class="form-select form-select-sm" style="width:160px">@foreach($typesBackup as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
        <button class="btn btn-primary btn-sm"><i class="bi bi-database-down me-1"></i>Lancer une sauvegarde</button>
    </form>
    @endcan
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted">Dernière sauvegarde réussie</div>
        @if($synthese['derniere'])<div class="fw-bold">{{ $synthese['derniere']->reference }}</div><div class="small text-muted">{{ $synthese['derniere']->created_at->diffForHumans() }} · {{ $synthese['derniere']->tailleLisible() }}</div>
        @else<div class="fw-bold text-warning">Aucune</div>@endif
    </div></div></div>
    <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold {{ $synthese['echecs'] ? 'text-danger' : '' }}">{{ $synthese['echecs'] }}</div><div class="small text-muted">Sauvegardes en échec</div></div></div></div>
    <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="fs-5 fw-bold {{ $synthese['restaurations_attente'] ? 'text-warning' : '' }}">{{ $synthese['restaurations_attente'] }}</div><div class="small text-muted">Restaurations en attente</div></div></div></div>
</div>

<div class="row g-3">
    {{-- Registre des sauvegardes --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i>Registre des sauvegardes</div>
            <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Référence</th><th>Type</th><th>Statut</th><th class="text-end">Taille</th><th>Date</th><th></th></tr></thead>
                <tbody>
                @forelse ($backups as $b)
                    <tr>
                        <td class="font-monospace small">{{ $b->reference }}</td>
                        <td class="small">{{ \App\Models\Backup::TYPES[$b->type] ?? $b->type }} <span class="text-muted">({{ $b->declenchement }})</span></td>
                        <td><span class="badge bg-{{ $bs[$b->statut] ?? 'secondary' }}">{{ \App\Models\Backup::STATUTS[$b->statut] ?? $b->statut }}</span></td>
                        <td class="text-end small">{{ $b->statut === 'succes' ? $b->tailleLisible() : '—' }}</td>
                        <td class="small text-muted">{{ $b->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-end">@can('admin_technique.backup')@if($b->statut === 'succes' && $b->chemin)<a href="{{ route('sauvegardes.telecharger', $b) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-download"></i></a>@endif @endcan</td>
                    </tr>
                @empty<tr><td colspan="6" class="text-center text-muted py-3">Aucune sauvegarde.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
        <div class="mt-2">{{ $backups->links() }}</div>
    </div>

    {{-- Planifications --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-week me-1"></i>Planifications</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Libellé</th><th>Fréquence</th><th>Rétention</th><th></th></tr></thead>
                <tbody>
                @forelse ($schedules as $s)
                    <tr><td class="small">{{ $s->libelle }}</td><td class="small">{{ \App\Models\BackupSchedule::FREQUENCES[$s->frequence] ?? $s->frequence }} · {{ $s->heure }}</td>
                        <td class="small">{{ $s->retention_jours }} j</td><td>@if($s->actif)<span class="badge bg-success-subtle text-success-emphasis">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif</td></tr>
                @empty<tr><td colspan="4" class="text-center text-muted py-3">Aucune planification.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>

{{-- Restaurations --}}
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-database-up me-1"></i>Restaurations (workflow contrôlé)</span>
        @can('admin_technique.restore')
        <form method="POST" action="{{ route('sauvegardes.restaurations.demander') }}" class="d-flex gap-2">
            @csrf
            <select name="backup_id" class="form-select form-select-sm" style="width:160px"><option value="">— Sauvegarde —</option>@foreach($backups as $b)@if($b->statut==='succes')<option value="{{ $b->id }}">{{ $b->reference }}</option>@endif @endforeach</select>
            <select name="type" class="form-select form-select-sm" style="width:150px">@foreach($typesRestore as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <input name="motif" class="form-control form-control-sm" placeholder="Motif *" required style="width:160px">
            <button class="btn btn-outline-primary btn-sm">Demander</button>
        </form>
        @endcan
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Réf.</th><th>Type</th><th>Statut</th><th>Demandeur</th><th>Valideur</th><th>Point</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($restaurations as $r)
            <tr>
                <td class="font-monospace small">{{ $r->reference }}</td>
                <td class="small">{{ \App\Models\RestoreOperation::TYPES[$r->type] ?? $r->type }}</td>
                <td><span class="badge bg-{{ $rs($r->statut) }}">{{ \App\Models\RestoreOperation::STATUTS[$r->statut] ?? $r->statut }}</span></td>
                <td class="small">{{ $r->demandeur?->name ?? '—' }}</td>
                <td class="small">{{ $r->validateur?->name ?? '—' }}</td>
                <td class="small text-muted">{{ $r->point_restauration }}</td>
                <td class="text-end text-nowrap">
                    @can('admin_technique.restore')
                    @if($r->statut === 'demande')
                        <form method="POST" action="{{ route('sauvegardes.restaurations.valider', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success py-0" title="Valider"><i class="bi bi-check2"></i></button></form>
                        <form method="POST" action="{{ route('sauvegardes.restaurations.rejeter', $r) }}" class="d-inline" onsubmit="this.motif_rejet.value=prompt('Motif du rejet ?')||'';return this.motif_rejet.value!=='';">@csrf<input type="hidden" name="motif_rejet"><button class="btn btn-sm btn-outline-danger py-0" title="Rejeter"><i class="bi bi-x"></i></button></form>
                    @elseif($r->statut === 'validee')
                        <form method="POST" action="{{ route('sauvegardes.restaurations.executee', $r) }}" class="d-inline" onsubmit="return confirm('Confirmer que la restauration a été exécutée ?')">@csrf<button class="btn btn-sm btn-outline-primary py-0">Marquer exécutée</button></form>
                    @endif
                    @endcan
                </td>
            </tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-3">Aucune restauration.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
