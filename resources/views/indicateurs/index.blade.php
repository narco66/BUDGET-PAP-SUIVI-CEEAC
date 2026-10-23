@extends('layouts.app')
@section('titre', 'Indicateurs · '.$config['label'])

@section('contenu')
<div class="mb-3">
    <span class="badge bg-secondary">{{ strtoupper($config['label']) }}</span>
    <span class="rbm-code">{{ $noeud->code }}</span> <strong>{{ $noeud->intitule }}</strong>
    <a href="{{ route('rbm.arbre', $exerciceId) }}" class="btn btn-sm btn-light float-end"><i class="bi bi-arrow-left me-1"></i>Retour à l'arbre</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        @forelse ($indicateurs as $i)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <code>{{ $i->code }}</code> <strong>{{ $i->libelle }}</strong>
                    <span class="badge bg-info-subtle text-info-emphasis ms-1">{{ $i->typeIndicateur?->libelle ?? $i->type }}</span>
                    @if($i->uniteMesure)<span class="badge bg-light text-dark">{{ $i->uniteMesure->symbole ?? $i->uniteMesure->code }}</span>@elseif($i->unite)<span class="badge bg-light text-dark">{{ $i->unite }}</span>@endif
                </div>
                <div class="d-flex gap-1">
                @can('indicateur.modifier')
                <a href="{{ route('rbm.indicateurs.edit', $i) }}?exercice={{ $exerciceId }}" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a>
                @endcan
                @can('ged.voir')
                <a href="{{ route('rbm.ged.show', ['indicateur', $i->id]) }}" class="btn btn-sm btn-outline-dark" title="GED"><i class="bi bi-folder2-open"></i></a>
                @endcan
                @can('indicateur.supprimer')
                <form action="{{ route('rbm.indicateurs.destroy', $i) }}" method="POST" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
                @endcan
                </div>
            </div>
            <div class="card-body p-0">
                <div class="px-3 py-2 small text-muted border-bottom">
                    Cible : {{ $i->valeur_cible ?? '—' }} · Réf. : {{ $i->valeur_reference ?? '—' }} · {{ $i->periodicite }}
                </div>
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Période</th><th>Réalisé</th><th>Taux</th><th>Feu</th><th>Tendance</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    @forelse($i->mesures as $m)
                        @php $tendance = $performance->tendance($i, $m->periode); @endphp
                        <tr>
                            <td><code>{{ $m->periode }}</code></td>
                            <td>{{ rtrim(rtrim($m->valeur_realisee,'0'),'.') }}</td>
                            <td>{{ $m->taux_atteinte }}%</td>
                            <td>
                                @if($m->feu==='vert')<span class="badge bg-success">Vert</span>
                                @elseif($m->feu==='orange')<span class="badge bg-warning text-dark">Orange</span>
                                @else<span class="badge bg-danger">Rouge</span>@endif
                            </td>
                            <td>
                                @if($tendance && $tendance['variation_pct'] !== null)
                                    <span class="small {{ $tendance['variation_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $tendance['variation_pct'] > 0 ? '+' : '' }}{{ $tendance['variation_pct'] }}%
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td><span class="badge bg-secondary">{{ $m->statut }}</span></td>
                            <td class="text-end">
                                @can('indicateur.valider')
                                    @if($m->statut==='saisie')
                                    <form action="{{ route('rbm.mesures.verifier', $m) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-outline-info">Vérifier</button></form>
                                    @endif
                                    @if(in_array($m->statut, ['saisie','verifiee']))
                                    <form action="{{ route('rbm.mesures.valider', $m) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success">Valider</button></form>
                                    @endif
                                @endcan
                                @can('indicateur.supprimer')
                                    @if($m->statut!=='validee')
                                    <form action="{{ route('rbm.mesures.destroy', $m) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                        @if($m->historiques->isNotEmpty())
                        <tr class="table-light">
                            <td colspan="7" class="small py-2">
                                <strong class="text-muted">Historique :</strong>
                                @foreach($m->historiques->take(5) as $h)
                                    <span class="me-3">{{ $h->action }} · {{ rtrim(rtrim($h->valeur_avant,'0'),'.') ?? '—' }} → {{ rtrim(rtrim($h->valeur_apres,'0'),'.') }} ({{ $h->created_at?->format('d/m/Y H:i') }})</span>
                                @endforeach
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-2">Aucune mesure.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @can('indicateur.creer')
                <div class="p-3 border-top bg-light">
                    <form action="{{ route('rbm.mesures.store', $i) }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-3"><input name="periode" class="form-control form-control-sm" placeholder="Période (2026-T1)" required></div>
                        <div class="col-md-3"><input name="valeur_realisee" type="number" step="any" class="form-control form-control-sm" placeholder="Valeur réalisée" required></div>
                        <div class="col-md-4"><input name="commentaire" class="form-control form-control-sm" placeholder="Commentaire"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Saisir</button></div>
                    </form>
                </div>
                @endcan
            </div>
        </div>
        @empty
        <div class="alert alert-light border">Aucun indicateur sur cet élément.</div>
        @endforelse
    </div>

    @can('indicateur.creer')
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-plus-circle me-1"></i>Nouvel indicateur</div>
            <div class="card-body">
                <form action="{{ route('rbm.indicateurs.store', [$niveau, $noeud->id]) }}?exercice={{ $exerciceId }}" method="POST">
                    @csrf
                    <div class="row g-2">
                        <div class="col-5"><input name="code" class="form-control form-control-sm" placeholder="Code *" required></div>
                        <div class="col-7"><input name="libelle" class="form-control form-control-sm" placeholder="Libellé *" required></div>
                        <div class="col-6">
                            <select name="type_indicateur_id" class="form-select form-select-sm" required>
                                <option value="">Type *</option>
                                @foreach($typesIndicateurs as $t)<option value="{{ $t->id }}">{{ $t->code }} — {{ $t->libelle }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="periodicite" class="form-select form-select-sm">
                                @foreach (['mensuel','trimestriel','semestriel','annuel','pluriannuel'] as $p)<option value="{{ $p }}" @selected($p==='trimestriel')>{{ $p }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="unite_mesure_id" class="form-select form-select-sm" required>
                                <option value="">Unité *</option>
                                @foreach($unitesMesure as $u)<option value="{{ $u->id }}">{{ $u->code }} ({{ $u->symbole }})</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6"><input name="valeur_reference" type="number" step="any" class="form-control form-control-sm" placeholder="Référence"></div>
                        <div class="col-6"><input name="valeur_cible" type="number" step="any" class="form-control form-control-sm" placeholder="Cible"></div>
                        <div class="col-12"><input name="source_verification" class="form-control form-control-sm" placeholder="Source (texte libre)"></div>
                        <div class="col-12"><textarea name="methode_calcul" class="form-control form-control-sm" rows="2" placeholder="Méthode de calcul"></textarea></div>
                    </div>
                    <button class="btn btn-primary btn-sm mt-2 w-100">Ajouter</button>
                </form>
            </div>
        </div>
    </div>
    @endcan
</div>
@endsection
