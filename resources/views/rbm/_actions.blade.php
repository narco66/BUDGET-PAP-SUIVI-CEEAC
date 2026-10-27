@php
    use App\Support\RbmStatutFiltre;
    use App\Support\RbmAuthorization;
    $statutRef = RbmStatutFiltre::statut($noeud, $niveau);
    $archive = $statutRef === 'archive';
    // Droits indépendants du nœud : calculés une fois par l'arbre ($droitsRbm), à défaut ici.
    $droitsRbm ??= [
        'indicateurs' => auth()->user()->can('indicateur.voir'),
        'historique' => auth()->user()->canAny(['rbm.consulter_journal', 'audit.voir']),
        'ged' => auth()->user()->can('ged.voir'),
    ];
    // Règle mémorisée pour la requête (équivalente au contrôle « rbm.action »).
    $peutRbm = fn (string $action) => RbmAuthorization::peut(auth()->user(), $niveau, $action);
@endphp
<div class="btn-group btn-group-sm">
    <span class="badge {{ RbmStatutFiltre::badgeClass($statutRef) }} align-self-center" title="Statut référentiel">{{ $statutRef }}</span>
    @if (! $archive)
        @if ($peutRbm('creer'))
            @if($enfant)
                <a href="{{ route('rbm.create', $enfant) }}?parent={{ $noeud->id }}&exercice={{ $ex }}" class="btn btn-outline-success" title="Ajouter un sous-élément"><i class="bi bi-plus-lg"></i></a>
            @endif
        @endif
    @endif
    @if ($droitsRbm['indicateurs'])
        <a href="{{ route('rbm.indicateurs.index', [$niveau, $noeud->id]) }}?exercice={{ $ex }}" class="btn btn-outline-secondary" title="Indicateurs"><i class="bi bi-graph-up-arrow"></i></a>
    @endif
    @if ($droitsRbm['historique'])
        <a href="{{ route('rbm.historique.noeud', [$niveau, $noeud->id]) }}?exercice={{ $ex }}" class="btn btn-outline-info" title="Historique"><i class="bi bi-clock-history"></i></a>
    @endif
    @if($niveau === 'activite')
        @if ($droitsRbm['ged'])
        <a href="{{ route('rbm.ged.show', ['activite', $noeud->id]) }}" class="btn btn-outline-dark" title="Documents GED"><i class="bi bi-folder2-open"></i></a>
        @endif
    @endif
    @if (! $archive)
        @if ($peutRbm('modifier'))
            <a href="{{ route('rbm.edit', [$niveau, $noeud->id]) }}?exercice={{ $ex }}" class="btn btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a>
        @endif
        {{-- Actions soumises par le formulaire unique de l'arbre (#rbm-action-form) : un formulaire par nœud
             multipliait le poids de la page (jeton CSRF et champs cachés × ~250 nœuds). --}}
        @if ($peutRbm('archiver'))
            <button type="button" class="btn btn-outline-warning" title="Archiver" data-rbm-action="{{ route('rbm.archiver', [$niveau, $noeud->id]) }}" data-rbm-confirmer="Archiver cet élément ?"><i class="bi bi-archive"></i></button>
        @endif
        @if ($peutRbm('supprimer'))
            <button type="button" class="btn btn-outline-danger" title="Supprimer" data-rbm-action="{{ route('rbm.destroy', [$niveau, $noeud->id]) }}" data-rbm-methode="DELETE" data-rbm-confirmer="Supprimer cet élément ?"><i class="bi bi-trash"></i></button>
        @endif
    @else
        @if ($peutRbm('restaurer'))
            <button type="button" class="btn btn-outline-success" title="Restaurer" data-rbm-action="{{ route('rbm.restaurer', [$niveau, $noeud->id]) }}"><i class="bi bi-arrow-counterclockwise"></i></button>
        @endif
    @endif
</div>
