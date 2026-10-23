<li>
    <div class="org-node type-{{ $node->type }}">
        <a href="{{ route('structures.show', $node) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-2">
            <i class="bi bi-diagram-3 text-muted"></i>
            <span>{{ $node->libelle }}</span>
            @if($node->sigle)<span class="badge bg-light text-dark sigle">{{ $node->sigle }}</span>@endif
            @unless($node->actif)<span class="badge bg-secondary">inactif</span>@endunless
        </a>
        <span class="org-actions">
            @can('create', App\Models\Structure::class)
                <a href="{{ route('structures.create', ['parent_id' => $node->id, 'retour' => 'organigramme']) }}"
                   class="btn btn-sm btn-outline-success py-0 px-1" title="Ajouter une sous-structure"><i class="bi bi-plus-lg"></i></a>
            @endcan
            @can('update', $node)
                <a href="{{ route('structures.edit', ['structure' => $node, 'retour' => 'organigramme']) }}"
                   class="btn btn-sm btn-outline-primary py-0 px-1" title="Modifier"><i class="bi bi-pencil"></i></a>
            @endcan
            @can('delete', $node)
                <form action="{{ route('structures.destroy', $node) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Désactiver « {{ $node->libelle }} » ?')">
                    @csrf @method('DELETE')
                    <input type="hidden" name="retour" value="organigramme">
                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Supprimer"><i class="bi bi-trash"></i></button>
                </form>
            @endcan
        </span>
    </div>
    @if ($node->enfantsRecursifs->isNotEmpty())
        <ul>
            @foreach ($node->enfantsRecursifs as $enfant)
                @include('structures._node', ['node' => $enfant])
            @endforeach
        </ul>
    @endif
</li>
