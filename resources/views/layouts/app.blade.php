<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titre', 'Accueil') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    @vite(['resources/css/ceeac.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<div class="d-flex">
    <nav class="sidebar text-white p-3 d-flex flex-column">
        <div class="d-flex align-items-center mb-4 px-1">
            <i class="bi bi-bank2 fs-3 me-2 text-white"></i>
            <div class="brand text-white lh-1">GEST-BUDGET<br><small class="fw-normal text-info">PAP · CEEAC</small></div>
        </div>
        <x-navigation.sidebar-menu />
        <div class="mt-auto pt-3 border-top border-secondary">
            <div class="small text-info">{{ auth()->user()->name }}</div>
            <div class="small text-secondary mb-2">{{ auth()->user()->getRoleNames()->implode(', ') }}</div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right me-1"></i>Déconnexion</button>
            </form>
        </div>
    </nav>

    <div class="flex-grow-1 contenu-principal">
        <div class="topbar text-white px-4 py-2 d-flex justify-content-between align-items-center shadow-sm">
            <h5 class="mb-0">@yield('titre', 'Accueil')</h5>
            <div class="d-flex align-items-center gap-3">
                @php $nbNonLues = auth()->user()->unreadNotifications()->whereNull('archive_at')->count(); @endphp
                <a href="{{ route('notifications.index') }}" class="text-white text-decoration-none position-relative" title="Notifications">
                    <i class="bi bi-bell fs-5"></i>
                    @if($nbNonLues > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $nbNonLues > 9 ? '9+' : $nbNonLues }}</span>
                    @endif
                </a>
                <span class="small">Exercice 2026 · XAF</span>
            </div>
        </div>

        <main class="p-4">
            <x-form.alerts />

            @yield('contenu')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@if ($errors->any())
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.addEventListener('DOMContentLoaded', function () {
    var cible = document.querySelector('.is-invalid') || document.getElementById('form-errors-summary');
    if (cible) { cible.scrollIntoView({ behavior: 'smooth', block: 'center' }); cible.focus?.(); }
});
</script>
@endif
@stack('scripts')
</body>
</html>
