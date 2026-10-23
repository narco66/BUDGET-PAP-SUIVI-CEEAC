@props(['items' => [], 'nested' => false])

<ul class="nav nav-pills flex-column gap-1 {{ $nested ? 'sidebar-submenu' : 'sidebar-menu' }}">
    @foreach ($items as $item)
        @if (($item['type'] ?? '') === 'section')
            <li class="section-title mt-3 mb-1 px-2">{{ $item['label'] }}</li>
        @elseif (($item['type'] ?? '') === 'group')
            <li class="sidebar-group">
                <button
                    class="nav-link sidebar-group-toggle w-100 {{ ($item['active'] ?? false) ? 'active' : '' }}"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#{{ $item['id'] }}"
                    aria-expanded="{{ ($item['active'] ?? false) ? 'true' : 'false' }}"
                    aria-controls="{{ $item['id'] }}"
                >
                    <span class="d-flex align-items-start gap-2">
                        <i class="bi {{ $item['icon'] }} sidebar-item-icon"></i>
                        <span class="sidebar-label">{{ $item['label'] }}</span>
                    </span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </button>
                <div id="{{ $item['id'] }}" class="collapse {{ ($item['active'] ?? false) ? 'show' : '' }}">
                    <x-navigation.sidebar-menu :items="$item['children']" :nested="true" />
                </div>
            </li>
        @else
            <li>
                <a class="nav-link {{ ($item['active'] ?? false) ? 'active' : '' }}" href="{{ $item['href'] }}">
                    <span class="d-flex align-items-start gap-2">
                        <i class="bi {{ $item['icon'] }} sidebar-item-icon"></i>
                        <span class="sidebar-label">{{ $item['label'] }}</span>
                    </span>
                </a>
            </li>
        @endif
    @endforeach
</ul>
