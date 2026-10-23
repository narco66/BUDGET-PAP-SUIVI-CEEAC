<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Référence</th>
                    <th>Titre</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Confidentialité</th>
                    <th>Dépôt</th>
                    <th>Taille</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($documents as $doc)
                <tr>
                    <td class="small font-monospace">{{ $doc->reference }}</td>
                    <td>{{ Str::limit($doc->titre, 45) }}</td>
                    <td class="small">{{ $doc->typeDocument?->libelle ?? '—' }}</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">{{ $doc->statut?->libelle() }}</span></td>
                    <td class="small">{{ $doc->niveau_confidentialite?->libelle() }}</td>
                    <td class="small">{{ $doc->date_depot?->format('d/m/Y H:i') }}</td>
                    <td class="small">{{ number_format($doc->taille / 1024, 0) }} Ko</td>
                    <td class="text-end">
                        <a href="{{ route('ged.documents.show', $doc) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('download', $doc)
                        <a href="{{ route('ged.documents.download', $doc) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Aucun document trouvé</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($documents, 'links'))
    <div class="card-footer bg-white">{{ $documents->links() }}</div>
    @endif
</div>
