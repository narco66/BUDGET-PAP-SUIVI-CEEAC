@php $active = $active ?? ''; @endphp
<nav class="nav nav-pills flex-wrap gap-1 mb-3 small">
    <a class="nav-link {{ $active === 'hub' ? 'active' : '' }}" href="{{ route('strategie.hub') }}"><i class="bi bi-compass me-1"></i>Hub</a>
    <a class="nav-link {{ $active === 'aide' ? 'active' : '' }}" href="{{ route('strategie.aide') }}"><i class="bi bi-book me-1"></i>Aide</a>
    <a class="nav-link {{ $active === 'dashboard' ? 'active' : '' }}" href="{{ route('strategie.dashboard.decisionnel') }}">Pilotage</a>
    <a class="nav-link {{ $active === 'matrice' ? 'active' : '' }}" href="{{ route('strategie.matrice') }}">Matrice</a>
    <a class="nav-link {{ $active === 'coherence' ? 'active' : '' }}" href="{{ route('strategie.rapport.coherence') }}">Cohérence</a>
    <a class="nav-link {{ $active === 'alignements' ? 'active' : '' }}" href="{{ route('strategie.alignements.index') }}">Alignements</a>
    <a class="nav-link {{ $active === 'pont-rbm' ? 'active' : '' }}" href="{{ route('strategie.pont-rbm') }}">Pont RBM</a>
    <a class="nav-link {{ $active === 'pont-pap' ? 'active' : '' }}" href="{{ route('strategie.pont-pap') }}">Pont PAP</a>
    <a class="nav-link {{ $active === 'programmations-pluri' ? 'active' : '' }}" href="{{ route('strategie.programmations-pluri.index') }}">Prog. pluri.</a>
    <a class="nav-link {{ $active === 'programmations-annuelles' ? 'active' : '' }}" href="{{ route('strategie.programmations-annuelles.index') }}">Prog. annuelle</a>
    <span class="nav-link disabled px-1">|</span>
    <a class="nav-link {{ $active === 'visions' ? 'active' : '' }}" href="{{ route('strategie.visions.index') }}">Visions</a>
    <a class="nav-link {{ $active === 'plans' ? 'active' : '' }}" href="{{ route('strategie.plans.index') }}">Plans</a>
    <a class="nav-link {{ $active === 'orientations' ? 'active' : '' }}" href="{{ route('strategie.orientations.index') }}">Orientations</a>
    <a class="nav-link {{ $active === 'programmes' ? 'active' : '' }}" href="{{ route('strategie.programmes.index') }}">Programmes</a>
    <a class="nav-link {{ $active === 'projets' ? 'active' : '' }}" href="{{ route('strategie.projets.index') }}">Projets</a>
    <a class="nav-link {{ $active === 'portefeuilles' ? 'active' : '' }}" href="{{ route('strategie.portefeuilles.index') }}">Portefeuilles</a>
    <span class="nav-link disabled px-1">|</span>
    <a class="nav-link {{ $active === 'objectifs' ? 'active' : '' }}" href="{{ route('strategie.objectifs.index') }}">Objectifs</a>
    <a class="nav-link {{ $active === 'indicateurs' ? 'active' : '' }}" href="{{ route('strategie.indicateurs.index') }}">Indicateurs</a>
    <a class="nav-link {{ $active === 'risques' ? 'active' : '' }}" href="{{ route('strategie.risques.index') }}">Risques</a>
</nav>
