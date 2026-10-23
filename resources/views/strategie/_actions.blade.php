@can('strategie.archive')
    @if(($item->statut ?? '') !== 'archive')
    <form method="POST" action="{{ route('strategie.archiver', [$entite, $item->id]) }}" class="d-inline">
        @csrf
        <button class="btn btn-sm btn-outline-dark" title="Archiver"><i class="bi bi-archive"></i></button>
    </form>
    @else
    <form method="POST" action="{{ route('strategie.restaurer', [$entite, $item->id]) }}" class="d-inline">
        @csrf
        <button class="btn btn-sm btn-outline-success" title="Restaurer"><i class="bi bi-arrow-counterclockwise"></i></button>
    </form>
    @endif
@endcan
@can('strategie.delete')
<form method="POST" action="{{ $destroyRoute }}" class="d-inline" onsubmit="return confirm('Supprimer définitivement ?')">
    @csrf @method('DELETE')
    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
</form>
@endcan
