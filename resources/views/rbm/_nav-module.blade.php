@php $active = $page ?? ''; @endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

    <nav aria-label="Navigation RBM">

        <div class="btn-group btn-group-sm">

            <a href="{{ route('rbm.hub', ['exercice' => $exercice->id]) }}" class="btn btn-outline-secondary @if($active==='hub') active @endif">Hub</a>

            <a href="{{ route('rbm.arbre', $exercice) }}" class="btn btn-outline-secondary @if($active==='arbre') active @endif">Arbre</a>

            <a href="{{ route('rbm.matrice', $exercice) }}" class="btn btn-outline-secondary @if($active==='matrice') active @endif">Matrice</a>

            <a href="{{ route('rbm.cadre-logique', $exercice) }}" class="btn btn-outline-secondary @if($active==='cadre') active @endif">Cadre logique</a>

            <a href="{{ route('rbm.chaine-resultats', $exercice) }}" class="btn btn-outline-secondary @if($active==='chaine') active @endif">Chaîne résultats</a>

            <a href="{{ route('rbm.theorie-changement', $exercice) }}" class="btn btn-outline-secondary @if($active==='theorie') active @endif">Théorie du changement</a>

            @canany(['rbm.consulter_journal','audit.voir'])

            <a href="{{ route('rbm.historique.index', $exercice) }}" class="btn btn-outline-secondary @if($active==='historique') active @endif">Historique</a>

            @endcanany

        </div>

    </nav>

    <div class="d-flex flex-wrap gap-2 align-items-center">

        @if($active === 'arbre')

        <form method="GET" action="{{ route('rbm.arbre', $exercice) }}" class="d-flex gap-2 align-items-center mb-0">

            <label class="small text-muted mb-0">Statut</label>

            <select name="statut" class="form-select form-select-sm" style="width:130px;" onchange="this.form.submit()">

                @foreach(\App\Support\RbmStatutFiltre::FILTRES as $s)

                    <option value="{{ $s }}" @selected(($statutFiltre ?? 'actif') === $s)>{{ ucfirst($s) }}</option>

                @endforeach

            </select>

        </form>

        @endif

        @if(isset($exercices) && $exercices->count() > 1 && $active === 'arbre')

        <form class="d-flex gap-2 align-items-center mb-0">

            <label class="small text-muted mb-0">Exercice</label>

            <select class="form-select form-select-sm" style="width:120px;" onchange="window.location.href=this.value">

                @foreach($exercices as $ex)

                    @php $url = route('rbm.arbre', $ex).'?statut='.urlencode($statutFiltre ?? 'actif'); @endphp

                    <option value="{{ $url }}" @selected($exercice->id === $ex->id)>{{ $ex->annee }}</option>

                @endforeach

            </select>

        </form>

        @else

        <span class="badge bg-primary">Exercice {{ $exercice->annee }}</span>

        @endif

    </div>

</div>

