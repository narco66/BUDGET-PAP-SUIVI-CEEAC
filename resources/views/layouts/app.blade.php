<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titre', 'Accueil') · {{ config('app.name') }}</title>
    <link href="{{ asset('vendor/bootstrap-5.3.3/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons-1.11.3/bootstrap-icons.min.css') }}" rel="stylesheet">
    @vite(['resources/css/ceeac.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<a href="#contenu" class="lien-evitement">Aller au contenu</a>
<div class="d-flex">
    {{-- Menu latéral : fixe sur ordinateur, escamotable (offcanvas) sur tablette portrait et mobile. --}}
    <nav id="menu-principal" class="sidebar offcanvas-lg offcanvas-start text-white p-3 d-flex flex-column" tabindex="-1" aria-label="Menu principal">
        <div class="d-flex align-items-center mb-4 px-1">
            <i class="bi bi-bank2 fs-3 me-2 text-white" aria-hidden="true"></i>
            <div class="brand text-white lh-1">GEST-BUDGET<br><small class="fw-normal text-info">PAP · CEEAC</small></div>
            <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menu-principal" aria-label="Fermer le menu"></button>
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
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button type="button" class="btn btn-sm btn-menu-mobile d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#menu-principal" aria-controls="menu-principal" aria-label="Ouvrir le menu">
                    <i class="bi bi-list fs-5" aria-hidden="true"></i>
                </button>
                <h1 class="h5 mb-0 text-truncate">@yield('titre', 'Accueil')</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                @php $nbNonLues = auth()->user()->unreadNotifications()->whereNull('archive_at')->count(); @endphp
                @can('notifications.view')
                {{-- Cloche + panneau rapide (référentiel Notifications § 8.1-8.2) : actualisée périodiquement, et en direct si Reverb est actif. --}}
                <div class="dropdown" id="cloche-notifications"
                     data-apercu="{{ route('notifications.apercu') }}" data-tout-lu="{{ route('notifications.tout-lu') }}"
                     data-utilisateur="{{ auth()->id() }}" data-cadence="{{ (int) config('notifications.temps_reel.actualisation_secondes', 60) }}">
                    <button type="button" class="btn btn-link text-white p-0 position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" aria-haspopup="true" id="cloche-bouton"
                            aria-label="Notifications : {{ $nbNonLues }} non lue(s)" title="Notifications{{ $nbNonLues > 0 ? ' — '.$nbNonLues.' non lue(s)' : '' }}">
                        <i class="bi bi-bell fs-5" aria-hidden="true"></i>
                        {{-- Compte exact jusqu'à 99 : plafonné à « 9+ », le badge ne bougeait pas après une lecture. --}}
                        <span id="cloche-compteur" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger {{ $nbNonLues > 0 ? '' : 'd-none' }}">{{ $nbNonLues > 99 ? '99+' : $nbNonLues }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow p-0 text-body" style="width: 390px; max-width: 94vw;" aria-labelledby="cloche-bouton">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong class="small">Notifications</strong>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="cloche-tout-lu">Tout marquer comme lu</button>
                        </div>
                        <div id="cloche-liste" class="list-group list-group-flush" style="max-height: 420px; overflow-y: auto;" aria-live="polite">
                            <div class="list-group-item small text-muted text-center py-3">Chargement…</div>
                        </div>
                        <a href="{{ route('notifications.index') }}" class="d-block text-center small py-2 border-top text-decoration-none">Voir toutes les notifications</a>
                    </div>
                </div>
                @endcan
                @php $exerciceCourant = \App\Models\Exercice::courant(); @endphp
                {{-- Exercice actif et devise lus en base (et non plus codés en dur). --}}
                <span class="small">@if($exerciceCourant)Exercice {{ $exerciceCourant->annee }} · {{ $exerciceCourant->devise ?: 'XAF' }}@else Aucun exercice actif @endif</span>
            </div>
        </div>

        <main class="p-4" id="contenu" tabindex="-1">
            <x-form.alerts />

            @yield('contenu')
        </main>
    </div>
</div>
<script src="{{ asset('vendor/bootstrap-5.3.3/bootstrap.bundle.min.js') }}"></script>
@if ($errors->any())
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.addEventListener('DOMContentLoaded', function () {
    var cible = document.querySelector('.is-invalid') || document.getElementById('form-errors-summary');
    if (cible) { cible.scrollIntoView({ behavior: 'smooth', block: 'center' }); cible.focus?.(); }
});
</script>
@endif
@can('notifications.view')
@php $reverbNotifications = config('broadcasting.default') === 'reverb' ? \App\Support\ReverbClientConfig::pourFront() : ['enabled' => false]; @endphp
@if (!empty($reverbNotifications['enabled']) && ! request()->routeIs('reporting.cockpit*'))
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>window.__reverbConfig = window.__reverbConfig || @json($reverbNotifications);</script>
@vite(['resources/js/echo.js'])
@endif
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
(function () {
    var racine = document.getElementById('cloche-notifications');
    if (!racine) { return; }
    var liste = document.getElementById('cloche-liste');
    var compteur = document.getElementById('cloche-compteur');
    var bouton = document.getElementById('cloche-bouton');
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var couleurs = { P1: 'danger', P2: 'warning', P3: 'info', P4: 'secondary' };
    var libellesPriorite = { P1: 'Critique', P2: 'Élevée', P3: 'Normale', P4: 'Faible' };

    function el(tag, classes, texte) {
        var n = document.createElement(tag);
        if (classes) { n.className = classes; }
        if (texte !== undefined && texte !== null) { n.textContent = texte; }   // texte uniquement : pas d'injection HTML
        return n;
    }
    function majCompteur(nb) {
        compteur.textContent = nb > 99 ? '99+' : String(nb);
        compteur.classList.toggle('d-none', nb <= 0);
        bouton.setAttribute('aria-label', 'Notifications : ' + nb + ' non lue(s)');
        bouton.title = 'Notifications' + (nb > 0 ? ' — ' + nb + ' non lue(s)' : '');
    }
    function rendre(items) {
        liste.replaceChildren();
        if (!items.length) { liste.appendChild(el('div', 'list-group-item small text-muted text-center py-3', 'Aucune notification.')); return; }
        items.forEach(function (n) {
            var a = el('a', 'list-group-item list-group-item-action py-2' + (n.lu ? '' : ' bg-light'));
            a.href = n.lien;
            var tete = el('div', 'd-flex justify-content-between align-items-start gap-2');
            var titre = el('div', 'small' + (n.lu ? '' : ' fw-semibold'), n.titre || 'Notification');
            tete.appendChild(titre);
            tete.appendChild(el('span', 'text-muted text-nowrap', n.cree_le_local)).style.fontSize = '.7rem';
            a.appendChild(tete);
            a.appendChild(el('div', 'small text-muted text-truncate', n.message || ''));
            var badges = el('div', 'd-flex flex-wrap gap-1 mt-1');
            if (!n.lu) { badges.appendChild(el('span', 'badge bg-primary', 'Non lue')); }
            badges.appendChild(el('span', 'badge bg-' + (couleurs[n.priorite] || 'secondary'), libellesPriorite[n.priorite] || n.priorite));
            if (n.module) { badges.appendChild(el('span', 'badge border text-secondary', String(n.module).toUpperCase())); }
            if (n.ressource && n.ressource.reference) { badges.appendChild(el('span', 'badge border text-secondary', n.ressource.reference)); }
            if (n.action) { badges.appendChild(el('span', 'badge ' + (n.action.disponible ? 'bg-warning text-dark' : 'bg-success-subtle text-success-emphasis'), n.action.libelle)); }
            a.appendChild(badges);
            liste.appendChild(a);
        });
    }
    function charger() {
        return fetch(racine.dataset.apercu, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
            .then(function (d) { majCompteur(d.non_lues); rendre(d.items || []); })
            .catch(function () {
                liste.replaceChildren(el('div', 'list-group-item small text-danger text-center py-3', 'Notifications momentanément indisponibles.'));
            });
    }
    document.getElementById('cloche-tout-lu').addEventListener('click', function () {
        fetch(racine.dataset.toutLu, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } })
            .then(charger);
    });
    racine.addEventListener('show.bs.dropdown', charger);
    // Actualisation périodique (reprise des notifications manquées) et au retour sur l'onglet.
    setInterval(function () { if (!document.hidden) { charger(); } }, Math.max(15, parseInt(racine.dataset.cadence, 10) || 60) * 1000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { charger(); } });
    // Temps réel : canal privé de l'utilisateur, si Echo/Reverb est disponible.
    window.addEventListener('load', function () {
        if (window.Echo && typeof window.Echo.private === 'function') {
            window.Echo.private('App.Models.User.' + racine.dataset.utilisateur).listen('.notification.creee', charger);
        }
    });
})();
</script>
@endcan
@stack('scripts')
</body>
</html>
