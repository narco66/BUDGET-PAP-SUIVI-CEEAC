@php
    $pages = [
        'hub' => ['route' => 'admin.interoperabilite.index', 'label' => 'Portail', 'icon' => 'speedometer2', 'perm' => 'api.view'],
        'apps' => ['route' => 'admin.interoperabilite.apps.index', 'label' => 'Applications', 'icon' => 'plug', 'perm' => 'api.admin'],
        'journal' => ['route' => 'admin.interoperabilite.journal', 'label' => 'Journal API', 'icon' => 'journal-text', 'perm' => 'api.monitor'],
        'webhooks' => ['route' => 'admin.interoperabilite.webhooks.index', 'label' => 'Webhooks', 'icon' => 'broadcast', 'perm' => 'api.admin'],
        'monitoring' => ['route' => 'admin.interoperabilite.monitoring', 'label' => 'Monitoring', 'icon' => 'graph-up', 'perm' => 'api.monitor'],
    ];
@endphp
<div class="btn-group btn-group-sm flex-wrap mb-3" role="group">
    @foreach ($pages as $key => $p)
        @can($p['perm'])
        <a href="{{ route($p['route']) }}" class="btn btn-outline-primary {{ ($page ?? '') === $key ? 'active' : '' }}">
            <i class="bi bi-{{ $p['icon'] }} me-1"></i>{{ $p['label'] }}
        </a>
        @endcan
    @endforeach
</div>
