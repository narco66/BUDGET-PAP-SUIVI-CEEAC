@extends('layouts.app')
@section('titre', "Centre d'exports")

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('reporting.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Reporting & Analyse</a>
        <h4 class="fw-bold mb-0">Centre d'exports</h4>
        <span class="text-muted small">Historique, versioning, workflow MOA et traçabilité</span>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Tous les types</option>
            @foreach ($types as $cle => $lib)<option value="{{ $cle }}" @selected(request('type') === $cle)>{{ $lib }}</option>@endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="format" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Tous formats</option>
            @foreach (['pdf' => 'PDF', 'excel' => 'Excel', 'word' => 'Word', 'csv' => 'CSV', 'pptx' => 'PowerPoint'] as $k => $v)<option value="{{ $k }}" @selected(request('format') === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>Référence</th><th>Type</th><th>Exercice</th><th>Format</th><th>Version</th>
            <th>Workflow</th><th>Généré par</th><th>Le</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse ($rapports as $r)
            <tr>
                <td class="font-monospace small">{{ $r->reference }}</td>
                <td class="small">{{ $types[$r->type] ?? $r->type }}</td>
                <td>{{ $r->exercice?->annee ?? '—' }}</td>
                <td><span class="badge bg-light text-dark text-uppercase">{{ $r->format }}</span></td>
                <td>v{{ $r->version }}</td>
                <td>
                    @if ($r->estPublie())
                        <span class="badge bg-primary"><i class="bi bi-megaphone me-1"></i>Publié</span>
                    @elseif ($r->estValide())
                        <span class="badge bg-info text-dark"><i class="bi bi-check-circle me-1"></i>Validé MOA</span>
                    @elseif ($r->estSigne())
                        <span class="badge bg-success"><i class="bi bi-patch-check me-1"></i>Signé</span>
                    @else
                        <span class="badge bg-secondary">Généré</span>
                    @endif
                    @if ($r->ged_indexe)
                        <span class="badge bg-light text-muted border" title="Indexé GED"><i class="bi bi-folder2-open"></i></span>
                    @endif
                </td>
                <td class="small">{{ $r->generePar?->name ?? '—' }}</td>
                <td class="small">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('reporting.telecharger', $r) }}" class="btn btn-sm btn-outline-primary py-0" title="Télécharger"><i class="bi bi-download"></i></a>
                    <a href="{{ $r->urlVerification() }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0" title="Vérifier"><i class="bi bi-qr-code"></i></a>
                    @can('validate', $r)
                        <form method="POST" action="{{ route('reporting.valider', $r) }}" class="d-inline">@csrf
                            <button class="btn btn-sm btn-outline-info py-0" title="Valider MOA"><i class="bi bi-check2-square"></i></button>
                        </form>
                    @endcan
                    @can('publish', $r)
                        <form method="POST" action="{{ route('reporting.publier', $r) }}" class="d-inline">@csrf
                            <button class="btn btn-sm btn-outline-primary py-0" title="Publier"><i class="bi bi-megaphone"></i></button>
                        </form>
                    @endcan
                    @can('sign', $r)
                        <form method="POST" action="{{ route('reporting.signer', $r) }}" class="d-inline">@csrf
                            <button class="btn btn-sm btn-outline-success py-0" title="Signer électroniquement"><i class="bi bi-pen"></i></button>
                        </form>
                    @endcan
                    @can('ged.creer')
                        @unless ($r->ged_indexe)
                            <form method="POST" action="{{ route('ged.rapports.indexer', $r) }}" class="d-inline">@csrf
                                <button class="btn btn-sm btn-outline-info py-0" title="Indexer dans la GED"><i class="bi bi-folder-plus"></i></button>
                            </form>
                        @endunless
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">Aucun rapport généré pour le moment.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>

<div class="mt-3">{{ $rapports->links() }}</div>
@endsection
