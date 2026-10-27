@php
    // Droits indépendants du nœud (StructurePolicy ne consulte que les permissions) : calculés une fois par l'arbre.
    $droitsOrg ??= [
        'creer' => auth()->user()->can('create', App\Models\Structure::class),
        'modifier' => auth()->user()->can('update', $node),
        'supprimer' => auth()->user()->can('delete', $node),
    ];
@endphp
<li>
    <div class="org-node type-{{ $node->type }}">
        <a href="{{ route('structures.show', $node) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-2">
            <i class="bi bi-diagram-3 text-muted"></i>
            <span>{{ $node->libelle }}</span>
            @if($node->sigle)<span class="badge bg-light text-dark sigle">{{ $node->sigle }}</span>@endif
            @unless($node->actif)<span class="badge bg-secondary">inactif</span>@endunless
        </a>
        <span class="org-actions">
            @if($droitsOrg['creer'])
                <a href="{{ route('structures.create', ['parent_id' => $node->id, 'retour' => 'organigramme']) }}"
                   class="btn btn-sm btn-outline-success py-0 px-1" title="Ajouter une sous-structure"><i class="bi bi-plus-lg"></i></a>
            @endif
            @if($droitsOrg['modifier'])
                <a href="{{ route('structures.edit', ['structure' => $node, 'retour' => 'organigramme']) }}"
                   class="btn btn-sm btn-outline-primary py-0 px-1" title="Modifier"><i class="bi bi-pencil"></i></a>
            @endif
            @if($droitsOrg['supprimer'])
                <form action="{{ route('structures.destroy', $node) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Désactiver « {{ $node->libelle }} » ?')">
                    @csrf @method('DELETE')
                    <input type="hidden" name="retour" value="organigramme">
                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Supprimer"><i class="bi bi-trash"></i></button>
                </form>
            @endif
        </span>
    </div>
    @if ($node->enfantsRecursifs->isNotEmpty())
        <ul>
            @foreach ($node->enfantsRecursifs as $enfant)
                @include('structures._node', ['node' => $enfant, 'droitsOrg' => $droitsOrg])
            @endforeach
        </ul>
    @endif
</li>
