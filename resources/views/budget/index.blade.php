@extends('layouts.app')
@section('titre', 'Budget · Exercice '.$exercice?->annee)

@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('contenu')
@if(!empty($enveloppe))
@php $c = ['brouillon'=>'secondary','en_validation'=>'warning','valide'=>'info','adopte'=>'success','rejete'=>'danger','en_revision'=>'primary'][$enveloppe->statut] ?? 'secondary'; @endphp
<div class="alert alert-{{ $enveloppe->statut === 'adopte' ? 'success' : ($lignesVerrouillees ? 'warning' : 'light') }} border-0 shadow-sm d-flex justify-content-between align-items-center mb-3">
    <div>
        <strong>Enveloppe {{ $enveloppe->reference }}</strong>
        <span class="badge bg-{{ $c }} ms-2">{{ str_replace('_',' ',$enveloppe->statut) }}</span>
        @if($lignesVerrouillees)<span class="small text-muted ms-2">— lignes verrouillées</span>@endif
    </div>
    @can('view', $enveloppe)
    <a href="{{ route('budget.enveloppe.show', $enveloppe) }}" class="btn btn-sm btn-outline-primary">Circuit d'adoption</a>
    <a href="{{ route('budget.enveloppe.rapport', $enveloppe) }}" class="btn btn-sm btn-outline-secondary ms-1">Rapport cycle</a>
    @endcan
</div>
@elseif($exercice)
<div class="alert alert-light border mb-3 d-flex justify-content-between align-items-center">
    <span class="text-muted small">Aucune enveloppe budgétaire pour cet exercice.</span>
    @can('create', \App\Models\Budget::class)
    <a href="{{ route('budget.enveloppe.create') }}" class="btn btn-sm btn-primary">Créer l'enveloppe</a>
    @endcan
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <select name="exercice" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
            @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id===$ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
        </select>
        <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Code / intitulé…" style="width:200px;">
        <select name="structure" class="form-select form-select-sm" style="width:200px;" onchange="this.form.submit()">
            <option value="">Toutes structures</option>
            @foreach ($structures as $s)<option value="{{ $s->id }}" @selected(request('structure')==$s->id)>{{ $s->libelle }}</option>@endforeach
        </select>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex gap-2">
        @can('ligne_budgetaire.exporter')<a href="{{ route('budget.export', ['exercice'=>$exercice?->id]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Exporter</a>@endcan
        <a href="{{ route('budget.import.form') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-upload me-1"></i>Importer</a>
        @can('ligne_budgetaire.creer')
            @unless($lignesVerrouillees ?? false)
            <a href="{{ route('budget.create', ['exercice'=>$exercice?->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouvelle ligne</a>
            @endunless
        @endcan
    </div>
</div>

{{-- Bandeau de totaux --}}
<div class="row g-3 mb-3">
    @foreach ([['Ouvert','o','primary'],['Engagé','e','warning'],['Liquidé','l','info'],['Ordonnancé','ord','secondary'],['Payé','p','dark']] as [$lib,$k,$col])
    <div class="col">
        <div class="card border-0 shadow-sm"><div class="card-body py-2">
            <div class="text-muted small">{{ $lib }}</div>
            <div class="fw-bold text-{{ $col }}">{{ $fmt($totaux->$k ?? 0) }}</div>
        </div></div>
    </div>
    @endforeach
    <div class="col">
        <div class="card border-0 shadow-sm bg-success-subtle"><div class="card-body py-2">
            <div class="text-muted small">Disponible</div>
            <div class="fw-bold text-success">{{ $fmt(($totaux->o ?? 0) - ($totaux->e ?? 0)) }}</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Structure</th><th class="text-end">Ouvert</th><th class="text-end">Engagé</th><th class="text-end">Disponible</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($lignes as $l)
                        <tr>
                            <td><code>{{ $l->code }}</code><br><small class="text-muted">{{ $l->rubrique?->code }}</small></td>
                            <td>{{ \Illuminate\Support\Str::limit($l->intitule, 50) }}</td>
                            <td class="small text-muted">{{ $l->structure?->sigle ?? '—' }}</td>
                            <td class="text-end">{{ $fmt($l->montant_ouvert) }}</td>
                            <td class="text-end">{{ $fmt($l->montant_engage) }}</td>
                            <td class="text-end fw-semibold {{ $l->disponible < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($l->disponible) }}</td>
                            <td class="text-end">
                                @can('ligne_budgetaire.modifier')<a href="{{ route('budget.edit', $l) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                                @can('ligne_budgetaire.supprimer')<form action="{{ route('budget.destroy', $l) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>@endcan
                            </td>
                        </tr>
                    @empty<tr><td colspan="7" class="text-center text-muted py-4">Aucune ligne budgétaire. Importez le budget {{ $exercice?->annee }} ou créez une ligne.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-2">{{ $lignes->links() }}</div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-tags me-1"></i>Par nature (Titre)</div>
            <ul class="list-group list-group-flush">
                @forelse ($parNature as $n)
                    <li class="list-group-item d-flex justify-content-between small">
                        <span>{{ $n->code }} · {{ \Illuminate\Support\Str::limit($n->intitule, 24) }} <span class="badge bg-light text-dark">{{ $n->sens }}</span></span>
                        <span class="fw-semibold">{{ $fmt($n->ouvert) }}</span>
                    </li>
                @empty<li class="list-group-item text-muted small">—</li>@endforelse
            </ul>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-3 me-1"></i>Par structure</div>
            <ul class="list-group list-group-flush">
                @forelse ($parStructure as $s)
                    <li class="list-group-item d-flex justify-content-between small">
                        <span>{{ \Illuminate\Support\Str::limit($s->libelle, 26) }}</span>
                        <span class="fw-semibold">{{ $fmt($s->ouvert) }}</span>
                    </li>
                @empty<li class="list-group-item text-muted small">—</li>@endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
