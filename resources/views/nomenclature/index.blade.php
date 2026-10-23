@extends('layouts.app')
@section('titre', 'Nomenclature budgétaire')

@push('styles')<style>
    .nom-tree details { margin:.15rem 0; }
    .nom-tree summary { cursor:pointer; padding:.25rem .4rem; border-radius:.3rem; }
    .nom-tree summary:hover { background:#eef3f9; }
    .nom-tree .lvl-article { margin-left:1.1rem; }
    .nom-tree .lvl-paragraphe { margin-left:2.2rem; }
    .nom-tree .lvl-rubrique { margin-left:3.3rem; font-size:.85rem; padding:.1rem .4rem; }
    .nom-code { font-family:monospace; color:#0a4a8f; }
</style>@endpush

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Nomenclature budgétaire</h4>
        <span class="text-muted small">Chapitre → Article → Paragraphe → Ligne (de nomenclature)</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('nomenclature.tableau') }}" class="btn btn-outline-info btn-sm"><i class="bi bi-speedometer2 me-1"></i>Tableau de bord</a>
        @can('nomenclature.creer')
        <a href="{{ route('nomenclature.assistant') }}" class="btn btn-success btn-sm"><i class="bi bi-lightning-charge me-1"></i>Saisie assistée</a>
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus-lg me-1"></i>Ajouter</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('nomenclature.create', 'titre') }}">Chapitre</a></li>
                <li><a class="dropdown-item" href="{{ route('nomenclature.create', 'article') }}">Article</a></li>
                <li><a class="dropdown-item" href="{{ route('nomenclature.create', 'paragraphe') }}">Paragraphe</a></li>
                <li><a class="dropdown-item" href="{{ route('nomenclature.create', 'rubrique') }}">Ligne</a></li>
            </ul>
        </div>
        @endcan
        @can('nomenclature.exporter')
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Exporter</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('reporting.generer', ['type' => 'nomenclature', 'format' => 'pdf']) }}">PDF</a></li>
                <li><a class="dropdown-item" href="{{ route('reporting.generer', ['type' => 'nomenclature', 'format' => 'excel']) }}">Excel</a></li>
                <li><a class="dropdown-item" href="{{ route('reporting.generer', ['type' => 'nomenclature', 'format' => 'csv']) }}">CSV</a></li>
            </ul>
        </div>
        @endcan
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#arbre">Arborescence</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tableau">Tableau des lignes</button></li>
</ul>

<div class="tab-content">
    {{-- Arborescence dynamique --}}
    <div class="tab-pane fade show active" id="arbre">
        <form method="GET" class="mb-3">
            <select name="sens" class="form-select form-select-sm" style="max-width:240px" onchange="this.form.submit()">
                <option value="">Tous les sens</option>
                @foreach ($sensListe as $k => $v)<option value="{{ $k }}" @selected(request('sens') === $k)>{{ $v }}</option>@endforeach
            </select>
        </form>
        <div class="card border-0 shadow-sm"><div class="card-body nom-tree">
            @forelse ($arbre as $titre)
                <details open>
                    <summary><span class="badge bg-primary">{{ $sensListe[$titre->sens] ?? $titre->sens }}</span>
                        <span class="nom-code">{{ $titre->code }}</span> — {{ $titre->intitule }}
                        <a href="{{ route('nomenclature.show', ['niveau' => 'titre', 'id' => $titre->id]) }}" class="small ms-1"><i class="bi bi-eye"></i></a></summary>
                    @foreach ($titre->articles as $article)
                        <details class="lvl-article">
                            <summary><i class="bi bi-folder text-warning me-1"></i><span class="nom-code">{{ $article->code }}</span> — {{ $article->intitule }}</summary>
                            @foreach ($article->paragraphes as $paragraphe)
                                <details class="lvl-paragraphe">
                                    <summary><i class="bi bi-folder2 text-success me-1"></i><span class="nom-code">{{ $paragraphe->code }}</span> — {{ $paragraphe->intitule }}</summary>
                                    @foreach ($paragraphe->rubriques as $rubrique)
                                        <div class="lvl-rubrique"><i class="bi bi-dot"></i><span class="nom-code">{{ $rubrique->code }}</span> — {{ $rubrique->intitule }}
                                            <a href="{{ route('nomenclature.show', ['niveau' => 'rubrique', 'id' => $rubrique->id]) }}" class="ms-1 text-muted"><i class="bi bi-eye"></i></a>
                                        </div>
                                    @endforeach
                                </details>
                            @endforeach
                        </details>
                    @endforeach
                </details>
            @empty
                <p class="text-muted text-center py-4 mb-0">Aucun élément de nomenclature.</p>
            @endforelse
        </div></div>
    </div>

    {{-- Vue tabulaire des rubriques --}}
    <div class="tab-pane fade" id="tableau">
        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="archives" value="{{ $archives ? 1 : 0 }}">
            <div class="col-auto"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Rechercher code ou libellé…"></div>
            <div class="col-auto"><select name="sens" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Tous sens</option>
                @foreach ($sensListe as $k => $v)<option value="{{ $k }}" @selected(request('sens') === $k)>{{ $v }}</option>@endforeach
            </select></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
            <div class="col-auto">
                <a href="{{ request()->fullUrlWithQuery(['archives' => $archives ? 0 : 1]) }}" class="btn btn-sm {{ $archives ? 'btn-warning' : 'btn-outline-secondary' }}">
                    <i class="bi bi-archive me-1"></i>{{ $archives ? 'Voir les actives' : 'Voir les archivées' }}
                </a>
            </div>
        </form>
        <div class="card border-0 shadow-sm"><div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Ligne</th><th>Paragraphe</th><th>Article</th><th>Chapitre</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($rubriques as $r)
                    <tr class="{{ $archives ? 'text-muted' : '' }}">
                        <td class="nom-code">{{ $r->code }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($r->intitule, 40) }}</td>
                        <td class="small">{{ $r->paragraphe?->code }}</td>
                        <td class="small">{{ $r->paragraphe?->article?->code }}</td>
                        <td class="small">{{ $r->paragraphe?->article?->titre?->code }}</td>
                        <td>
                            @if (($r->statut ?? 'valide') === 'valide')
                                <span class="badge bg-success-subtle text-success-emphasis">{{ $statutListe['valide'] }}</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning-emphasis">{{ $statutListe['brouillon'] }}</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('nomenclature.show', ['niveau' => 'rubrique', 'id' => $r->id]) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
                            @if ($archives)
                                @can('nomenclature.modifier')<form method="POST" action="{{ route('nomenclature.restaurer', ['niveau' => 'rubrique', 'id' => $r->id]) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success py-0" title="Restaurer"><i class="bi bi-arrow-counterclockwise"></i></button></form>@endcan
                            @else
                                @if (($r->statut ?? 'valide') !== 'valide')
                                    @can('nomenclature.valider')<form method="POST" action="{{ route('nomenclature.valider', ['niveau' => 'rubrique', 'id' => $r->id]) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success py-0" title="Valider"><i class="bi bi-check2-circle"></i></button></form>@endcan
                                @endif
                                @can('nomenclature.modifier')<a href="{{ route('nomenclature.edit', ['niveau' => 'rubrique', 'id' => $r->id]) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>@endcan
                                @can('nomenclature.supprimer')<form method="POST" action="{{ route('nomenclature.archiver', ['niveau' => 'rubrique', 'id' => $r->id]) }}" class="d-inline" onsubmit="return confirm('Archiver cette ligne ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0" title="Archiver"><i class="bi bi-archive"></i></button></form>@endcan
                            @endif
                        </td>
                    </tr>
                @empty<tr><td colspan="7" class="text-center text-muted py-4">Aucune ligne{{ $archives ? ' archivée' : '' }}.</td></tr>@endforelse
                </tbody>
            </table>
        </div></div>
        <div class="mt-3">{{ $rubriques->links() }}</div>
    </div>
</div>
@endsection
