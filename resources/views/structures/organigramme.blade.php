@extends('layouts.app')
@section('titre', 'Organigramme institutionnel')

@push('styles')
<style>
    .org-toolbar { gap: .5rem; }
    .org-tree, .org-tree ul { list-style: none; padding-left: 1.2rem; }
    .org-tree ul { border-left: 1px solid #d7e0ea; margin-left: .6rem; }
    .org-node { display: inline-flex; align-items: center; gap: .5rem; padding: .4rem .75rem; border: 1px solid #cdd9e8; border-radius: .5rem; background: #fff; margin: .25rem 0; }
    .org-node .sigle { font-size: .7rem; }
    .org-actions { display: inline-flex; gap: .2rem; opacity: 0; transition: opacity .15s; }
    .org-node:hover .org-actions { opacity: 1; }
    .org-actions form { margin: 0; }
    .type-presidence,.type-vice_presidence,.type-secretariat_general { border-left: 4px solid #0a4a8f; }
    .type-departement { border-left: 4px solid #198754; }
    .type-direction { border-left: 4px solid #fd7e14; }
    .type-service,.type-cellule { border-left: 4px solid #6c757d; }
    .org-graphic .org-tree { display: flex; gap: 1.25rem; align-items: flex-start; overflow-x: auto; padding-bottom: 1rem; }
    .org-graphic .org-tree > li { min-width: 280px; }
    .org-graphic .org-tree ul { padding-left: .75rem; }
    .org-hidden { display: none !important; }
    .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem; }
    .metric { border: 1px solid #e1e7ef; border-radius: .5rem; padding: .75rem; background: #fff; }
    @media print {
        @page { size: A3 landscape; margin: 12mm; }
        .org-toolbar, .org-actions, .btn, nav, aside { display: none !important; }
        .card { border: 0 !important; box-shadow: none !important; }
        .org-tree { font-size: 11px; }
    }
</style>
@endpush

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 org-toolbar">
    <div>
        <h4 class="fw-bold mb-0">Organigramme institutionnel</h4>
        <span class="text-muted small">Cadre organique de la Commission de la CEEAC</span>
    </div>
    <div class="d-flex flex-wrap org-toolbar">
        <input id="orgSearch" type="search" class="form-control form-control-sm" style="width: 240px" placeholder="Rechercher">
        <div class="btn-group btn-group-sm" role="group" aria-label="Vue organigramme">
            <button type="button" class="btn btn-outline-secondary active" data-org-view="tree"><i class="bi bi-list-ul"></i></button>
            <button type="button" class="btn btn-outline-secondary" data-org-view="graphic"><i class="bi bi-diagram-3"></i></button>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="exportSvg"><i class="bi bi-filetype-svg me-1"></i>SVG</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="exportPng"><i class="bi bi-filetype-png me-1"></i>PNG</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="exportPdf"><i class="bi bi-filetype-pdf me-1"></i>PDF</button>
        @include('structures._export_buttons')
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>A3</button>
        @can('create', App\Models\Structure::class)
            <a href="{{ route('structures.create', ['retour' => 'organigramme']) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle racine</a>
        @endcan
    </div>
</div>

<div class="metric-grid mb-3">
    <div class="metric"><div class="text-muted small">Departements</div><div class="fs-5 fw-bold">{{ $indicateurs['effectifs_departements']->count() }}</div></div>
    <div class="metric"><div class="text-muted small">Directions</div><div class="fs-5 fw-bold">{{ $indicateurs['effectifs_directions']->count() }}</div></div>
    <div class="metric"><div class="text-muted small">Services</div><div class="fs-5 fw-bold">{{ $indicateurs['effectifs_services']->count() }}</div></div>
    <div class="metric"><div class="text-muted small">Budget departements</div><div class="fs-5 fw-bold">{{ $indicateurs['budget_departements']->count() }}</div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body" id="orgCanvas">
        @if ($racines->isEmpty())
            <p class="text-muted text-center py-4 mb-0">Aucune structure. Creez la premiere structure racine.</p>
        @endif
        <ul class="org-tree">
            @foreach ($racines as $racine)
                @include('structures._node', ['node' => $racine])
            @endforeach
        </ul>
    </div>
</div>
@endsection

@push('scripts')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
(() => {
    const canvas = document.getElementById('orgCanvas');
    const search = document.getElementById('orgSearch');

    search?.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        canvas.querySelectorAll('li').forEach((li) => {
            const text = li.innerText.toLowerCase();
            li.classList.toggle('org-hidden', term.length > 0 && !text.includes(term));
        });
    });

    document.querySelectorAll('[data-org-view]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-org-view]').forEach((b) => b.classList.remove('active'));
            button.classList.add('active');
            canvas.classList.toggle('org-graphic', button.dataset.orgView === 'graphic');
        });
    });

    const svgMarkup = () => {
        const clone = canvas.cloneNode(true);
        clone.querySelectorAll('.org-actions').forEach((node) => node.remove());
        const html = new XMLSerializer().serializeToString(clone);
        return `<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1000"><foreignObject width="100%" height="100%">${html}</foreignObject></svg>`;
    };

    const download = (name, type, content) => {
        const blob = new Blob([content], {type});
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = name;
        link.click();
        URL.revokeObjectURL(link.href);
    };

    document.getElementById('exportSvg')?.addEventListener('click', () => download('organigramme-ceeac.svg', 'image/svg+xml', svgMarkup()));
    document.getElementById('exportPdf')?.addEventListener('click', () => window.print());
    document.getElementById('exportPng')?.addEventListener('click', () => {
        const img = new Image();
        const svg = svgMarkup();
        img.onload = () => {
            const pngCanvas = document.createElement('canvas');
            pngCanvas.width = 1600;
            pngCanvas.height = 1000;
            pngCanvas.getContext('2d').drawImage(img, 0, 0);
            pngCanvas.toBlob((blob) => {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'organigramme-ceeac.png';
                link.click();
                URL.revokeObjectURL(link.href);
            });
        };
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    });
})();
</script>
@endpush
