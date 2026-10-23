@php
    $fr = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $qs = http_build_query(array_merge(request()->except('page'), ['exercice' => $exercice->id]));
    $couleurs = ['warning' => '#fff3cd', 'orange' => '#ffe0b2', 'danger' => '#f8d7da'];
@endphp

<div class="card border-0 shadow-sm mt-4" id="activites-retard">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>Activités en retard
            <span class="badge bg-danger ms-1">{{ $retardSynthese['total'] }}</span>
        </h6>
        <div class="d-flex gap-2">
            <a href="{{ route('reporting.cockpit.retard.export', 'pdf') }}?{{ $qs }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i></a>
            <a href="{{ route('reporting.cockpit.retard.export', 'xlsx') }}?{{ $qs }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel"></i></a>
            <a href="{{ route('reporting.cockpit.retard.export', 'csv') }}?{{ $qs }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv"></i></a>
            <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i></button>
        </div>
    </div>
    <div class="card-body">
        {{-- Synthèse --}}
        <div class="row g-2 mb-3">
            <div class="col-md-4"><div class="border rounded p-2 text-center">
                <div class="text-muted small text-uppercase">Activités en retard</div><div class="fs-4 fw-bold text-danger">{{ $retardSynthese['total'] }}</div>
            </div></div>
            <div class="col-md-4"><div class="border rounded p-2 text-center">
                <div class="text-muted small text-uppercase">Budget concerné</div><div class="fs-5 fw-bold">{{ $fr($retardSynthese['montant']) }} <small class="text-muted">XAF</small></div>
            </div></div>
            <div class="col-md-4"><div class="border rounded p-2 text-center">
                <div class="text-muted small text-uppercase">Avancement moyen</div><div class="fs-4 fw-bold text-warning">{{ $retardSynthese['taux_moyen'] }}%</div>
            </div></div>
        </div>

        {{-- Filtres avancés --}}
        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="exercice" value="{{ $exercice->id }}">
            <div class="col-md-3">
                <select name="structure_id" class="form-select form-select-sm">
                    <option value="">Tous départements</option>
                    @foreach ($retardOptions['structures'] as $s)<option value="{{ $s->id }}" @selected(($retardFiltres['structure_id'] ?? null)==$s->id)>{{ $s->libelle }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="responsable_id" class="form-select form-select-sm">
                    <option value="">Tous responsables</option>
                    @foreach ($retardOptions['responsables'] as $u)<option value="{{ $u->id }}" @selected(($retardFiltres['responsable_id'] ?? null)==$u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="pilier_id" class="form-select form-select-sm">
                    <option value="">Tous piliers</option>
                    @foreach ($retardOptions['piliers'] as $p)<option value="{{ $p->id }}" @selected(($retardFiltres['pilier_id'] ?? null)==$p->id)>{{ $p->code }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="axe_id" class="form-select form-select-sm">
                    <option value="">Tous axes</option>
                    @foreach ($retardOptions['axes'] as $a)<option value="{{ $a->id }}" @selected(($retardFiltres['axe_id'] ?? null)==$a->id)>{{ $a->code }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="source_id" class="form-select form-select-sm">
                    <option value="">Toutes sources</option>
                    @foreach ($retardOptions['sources'] as $src)<option value="{{ $src->id }}" @selected(($retardFiltres['source_id'] ?? null)==$src->id)>{{ \Illuminate\Support\Str::limit($src->libelle, 22) }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><input type="date" name="du" value="{{ $retardFiltres['du'] ?? '' }}" class="form-control form-control-sm" placeholder="Échéance du"></div>
            <div class="col-md-3"><input type="date" name="au" value="{{ $retardFiltres['au'] ?? '' }}" class="form-control form-control-sm" placeholder="au"></div>
            <div class="col-md-6 d-flex gap-2">
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                <a href="{{ route('reporting.cockpit', ['exercice' => $exercice->id]) }}#activites-retard" class="btn btn-sm btn-outline-secondary">Réinitialiser</a>
            </div>
        </form>

        {{-- Légende --}}
        <div class="small text-muted mb-2">
            <span class="badge" style="background:#fff3cd;color:#664d03">1–15 j</span>
            <span class="badge" style="background:#ffe0b2;color:#7a3d00">16–30 j</span>
            <span class="badge bg-danger">&gt; 30 j</span>
            · cliquez sur une ligne pour ouvrir la fiche détaillée
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th><th>Activité</th><th>Pilier</th><th>Axe</th><th>Produit</th><th>Sous-produit</th>
                        <th>Département</th><th>Responsable</th><th>Début</th><th>Fin prévue</th>
                        <th class="text-end">Retard (j)</th><th class="text-end">Phys.</th><th class="text-end">Fin.</th>
                        <th class="text-end">Budget</th><th class="text-end">Consommé</th><th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($retards as $a)
                    @php
                        $j = $retardSvc->joursRetard($a);
                        $niv = $retardSvc->niveau($j);
                        $url = route('reporting.cockpit.activite', $a);
                    @endphp
                    <tr style="cursor:pointer;background:{{ $couleurs[$niv] }}" onclick="window.location='{{ $url }}'">
                        <td><a href="{{ $url }}" class="fw-semibold text-decoration-none" onclick="event.stopPropagation()">{{ $a->code }}</a></td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($a->intitule, 40) }}</td>
                        <td class="small">{{ $a->produit?->axe?->pilier?->code ?? '—' }}</td>
                        <td class="small">{{ $a->produit?->axe?->code ?? '—' }}</td>
                        <td class="small">{{ $a->produit?->code ?? '—' }}</td>
                        <td class="small">{{ $a->sousProduit?->code ?? '—' }}</td>
                        <td class="small">{{ $a->structure?->sigle ?? $a->structure?->libelle ?? '—' }}</td>
                        <td class="small">{{ $a->responsable?->name ?? '—' }}</td>
                        <td class="small">{{ $a->date_debut_prevue ? \Illuminate\Support\Carbon::parse($a->date_debut_prevue)->format('d/m/y') : '—' }}</td>
                        <td class="small">{{ $a->date_fin_prevue ? \Illuminate\Support\Carbon::parse($a->date_fin_prevue)->format('d/m/y') : '—' }}</td>
                        <td class="text-end"><span class="badge {{ $niv === 'danger' ? 'bg-danger' : 'text-dark' }}" style="{{ $niv !== 'danger' ? 'background:'.($niv==='orange'?'#fd9843':'#ffca2c') : '' }}">{{ $j }}</span></td>
                        <td class="text-end small">{{ (float) $a->taux_realisation_physique }}%</td>
                        <td class="text-end small">{{ (float) $a->taux_realisation_financier }}%</td>
                        <td class="text-end small">{{ $fr($a->montant_total) }}</td>
                        <td class="text-end small">{{ $fr($retardSvc->budgetConsomme($a)) }}</td>
                        <td class="small">{{ $a->statut }}</td>
                    </tr>
                @empty
                    <tr><td colspan="16" class="text-center text-muted py-4"><i class="bi bi-check-circle text-success me-1"></i>Aucune activité en retard détectée.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $retards->fragment('activites-retard')->links() }}</div>
    </div>
</div>
