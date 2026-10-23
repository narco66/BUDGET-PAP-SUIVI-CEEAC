@php
    use App\Support\RbmStatutFiltre;
    $statutRef = RbmStatutFiltre::statut($noeud, $niveau);
    $archive = $statutRef === 'archive';
@endphp
<div class="btn-group btn-group-sm">
    <span class="badge {{ RbmStatutFiltre::badgeClass($statutRef) }} align-self-center" title="Statut référentiel">{{ $statutRef }}</span>
    @if (! $archive)
        @can('rbm.action', [$niveau, 'creer'])
            @if($enfant)
                <a href="{{ route('rbm.create', $enfant) }}?parent={{ $noeud->id }}&exercice={{ $ex }}" class="btn btn-outline-success" title="Ajouter un sous-élément"><i class="bi bi-plus-lg"></i></a>
            @endif
        @endcan
    @endif
    @can('indicateur.voir')
        <a href="{{ route('rbm.indicateurs.index', [$niveau, $noeud->id]) }}?exercice={{ $ex }}" class="btn btn-outline-secondary" title="Indicateurs"><i class="bi bi-graph-up-arrow"></i></a>
    @endcan
    @canany(['rbm.consulter_journal','audit.voir'])
        <a href="{{ route('rbm.historique.noeud', [$niveau, $noeud->id]) }}?exercice={{ $ex }}" class="btn btn-outline-info" title="Historique"><i class="bi bi-clock-history"></i></a>
    @endcanany
    @if($niveau === 'activite')
        @can('ged.voir')
        <a href="{{ route('rbm.ged.show', ['activite', $noeud->id]) }}" class="btn btn-outline-dark" title="Documents GED"><i class="bi bi-folder2-open"></i></a>
        @endcan
    @endif
    @if (! $archive)
        @can('rbm.action', [$niveau, 'modifier'])
            <a href="{{ route('rbm.edit', [$niveau, $noeud->id]) }}?exercice={{ $ex }}" class="btn btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a>
        @endcan
        @can('rbm.action', [$niveau, 'archiver'])
            <form action="{{ route('rbm.archiver', [$niveau, $noeud->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Archiver cet élément ?')">
                @csrf
                <input type="hidden" name="exercice" value="{{ $ex }}">
                <button class="btn btn-outline-warning" title="Archiver"><i class="bi bi-archive"></i></button>
            </form>
        @endcan
        @can('rbm.action', [$niveau, 'supprimer'])
            <form action="{{ route('rbm.destroy', [$niveau, $noeud->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cet élément ?')">
                @csrf @method('DELETE')
                <input type="hidden" name="exercice" value="{{ $ex }}">
                <button class="btn btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button>
            </form>
        @endcan
    @else
        @can('rbm.action', [$niveau, 'restaurer'])
            <form action="{{ route('rbm.restaurer', [$niveau, $noeud->id]) }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="exercice" value="{{ $ex }}">
                <button class="btn btn-outline-success" title="Restaurer"><i class="bi bi-arrow-counterclockwise"></i></button>
            </form>
        @endcan
    @endif
</div>
