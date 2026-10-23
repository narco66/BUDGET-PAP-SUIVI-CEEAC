@php
    $critCouleur = fn ($n) => match($n) { 'critique' => 'danger', 'eleve' => 'warning', 'moyen' => 'info', default => 'secondary' };
    $statCouleur = fn ($s) => match($s) { 'ouvert' => 'danger', 'attenue' => 'warning', 'clos' => 'success', default => 'secondary' };
@endphp

@can('gererGar', $pap)
<div class="mb-4">
    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#pap-form-risque">
        <i class="bi bi-plus-circle me-1"></i>Nouveau risque
    </button>
    <div class="collapse mt-2" id="pap-form-risque">
        <div class="card card-body border">
            <form action="{{ route('paps.risques.store', $pap) }}" method="POST">
                @csrf
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label small">Code</label>
                        <input type="text" name="code" class="form-control form-control-sm" required maxlength="40" placeholder="R-001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Libellé</label>
                        <input type="text" name="libelle" class="form-control form-control-sm" required maxlength="255">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Activité liée</label>
                        <select name="activite_id" class="form-select form-select-sm">
                            <option value="">— Aucune —</option>
                            @foreach($activitesSelect as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Responsable</label>
                        <select name="responsable_user_id" class="form-select form-select-sm">
                            <option value="">— Non assigné —</option>
                            @foreach($responsables as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Probabilité</label>
                        <select name="probabilite" class="form-select form-select-sm" required>
                            <option value="faible">Faible</option>
                            <option value="moyenne" selected>Moyenne</option>
                            <option value="elevee">Élevée</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Impact</label>
                        <select name="impact" class="form-select form-select-sm" required>
                            <option value="faible">Faible</option>
                            <option value="moyen" selected>Moyen</option>
                            <option value="eleve">Élevé</option>
                            <option value="critique">Critique</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Statut</label>
                        <select name="statut" class="form-select form-select-sm" required>
                            <option value="ouvert" selected>Ouvert</option>
                            <option value="attenue">Atténué</option>
                            <option value="clos">Clos</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Mitigation</label>
                        <input type="text" name="mitigation" class="form-control form-control-sm" maxlength="500">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2 me-1"></i>Enregistrer le risque</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<h6 class="small fw-bold mb-2"><i class="bi bi-shield-exclamation me-1"></i>Registre des risques ({{ $risques->count() }})</h6>
<div class="table-responsive mb-4">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>Code</th>
                <th>Risque</th>
                <th>Criticité</th>
                <th>Statut</th>
                <th>Activité</th>
                <th>Responsable</th>
                @can('gererGar', $pap)<th></th>@endcan
            </tr>
        </thead>
        <tbody>
            @forelse($risques as $r)
            <tr>
                <td><code>{{ $r->code }}</code></td>
                <td>
                    <div class="fw-semibold">{{ Str::limit($r->libelle, 45) }}</div>
                    @if($r->mitigation)<div class="small text-muted">Mitigation : {{ Str::limit($r->mitigation, 60) }}</div>@endif
                </td>
                <td><span class="badge bg-{{ $critCouleur($r->niveauCriticite()) }}">{{ $r->niveauCriticite() }}</span></td>
                <td><span class="badge bg-{{ $statCouleur($r->statut) }}">{{ $r->statut }}</span></td>
                <td class="small">@if($r->activite)<code>{{ $r->activite->code }}</code>@else<span class="text-muted">—</span>@endif</td>
                <td class="small">{{ $r->responsable?->name ?? '—' }}</td>
                @can('gererGar', $pap)
                <td class="text-end">
                    <form action="{{ route('paps.risques.destroy', [$pap, $r]) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce risque ?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
                @endcan
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-3">Aucun risque enregistré pour ce PAP.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@can('gererGar', $pap)
<div class="mb-3">
    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#pap-form-mesure">
        <i class="bi bi-plus-circle me-1"></i>Nouvelle mesure corrective
    </button>
    <div class="collapse mt-2" id="pap-form-mesure">
        <div class="card card-body border">
            <form action="{{ route('paps.mesures.store', $pap) }}" method="POST">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small">Risque lié</label>
                        <select name="pap_risque_id" class="form-select form-select-sm">
                            <option value="">— Aucun —</option>
                            @foreach($risques as $r)
                                <option value="{{ $r->id }}">{{ $r->code }} — {{ Str::limit($r->libelle, 30) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Activité</label>
                        <select name="activite_id" class="form-select form-select-sm">
                            <option value="">— Aucune —</option>
                            @foreach($activitesSelect as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Échéance</label>
                        <input type="date" name="echeance" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Statut</label>
                        <select name="statut" class="form-select form-select-sm" required>
                            <option value="planifiee" selected>Planifiée</option>
                            <option value="en_cours">En cours</option>
                            <option value="realisee">Réalisée</option>
                            <option value="annulee">Annulée</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Responsable</label>
                        <select name="responsable_user_id" class="form-select form-select-sm">
                            <option value="">— Non assigné —</option>
                            @foreach($responsables as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" required></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-check2 me-1"></i>Enregistrer la mesure</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<h6 class="small fw-bold mb-2"><i class="bi bi-tools me-1"></i>Mesures correctives ({{ $mesures->count() }})</h6>
<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>Description</th>
                <th>Risque</th>
                <th>Activité</th>
                <th>Échéance</th>
                <th>Statut</th>
                <th>Responsable</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mesures as $m)
            <tr>
                <td class="small">{{ Str::limit($m->description, 60) }}</td>
                <td class="small">@if($m->risque)<code>{{ $m->risque->code }}</code>@else<span class="text-muted">—</span>@endif</td>
                <td class="small">@if($m->activite)<code>{{ $m->activite->code }}</code>@else<span class="text-muted">—</span>@endif</td>
                <td class="small">{{ $m->echeance?->format('d/m/Y') ?? '—' }}</td>
                <td><span class="badge bg-secondary">{{ str_replace('_', ' ', $m->statut) }}</span></td>
                <td class="small">{{ $m->responsable?->name ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-3">Aucune mesure corrective.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
