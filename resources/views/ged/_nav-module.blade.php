@php
    $pages = [
        'dashboard' => ['route' => 'ged.dashboard', 'label' => 'Tableau de bord', 'icon' => 'speedometer2'],
        'documents' => ['route' => 'ged.documents.index', 'label' => 'Documents', 'icon' => 'files'],
        'depot' => ['route' => 'ged.documents.create', 'label' => 'Dépôt', 'icon' => 'cloud-upload'],
        'recherche' => ['route' => 'ged.recherche', 'label' => 'Recherche', 'icon' => 'search'],
        'archives' => ['route' => 'ged.archives', 'label' => 'Archives', 'icon' => 'archive'],
        'journal' => ['route' => 'ged.journal', 'label' => 'Journal', 'icon' => 'journal-text'],
    ];
@endphp
<div class="btn-group btn-group-sm flex-wrap mb-3" role="group">
    @foreach ($pages as $key => $p)
        <a href="{{ route($p['route']) }}" class="btn btn-outline-primary {{ ($page ?? '') === $key ? 'active' : '' }}">
            <i class="bi bi-{{ $p['icon'] }} me-1"></i>{{ $p['label'] }}
        </a>
    @endforeach
</div>
