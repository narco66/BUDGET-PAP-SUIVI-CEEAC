{{--
    Liste de documents GED (registre, archives, confidentiels, expirés, partages, recherche).
    Paramètres : $documents (paginateur ou collection), $parPage (bool, sélecteur 20/50/100).
--}}
@php
$icones = ['pdf' => 'file-earmark-pdf text-danger', 'doc' => 'file-earmark-word text-primary', 'docx' => 'file-earmark-word text-primary',
    'xls' => 'file-earmark-excel text-success', 'xlsx' => 'file-earmark-excel text-success', 'csv' => 'file-earmark-spreadsheet text-success',
    'ppt' => 'file-earmark-ppt text-warning', 'pptx' => 'file-earmark-ppt text-warning', 'png' => 'file-earmark-image text-info',
    'jpg' => 'file-earmark-image text-info', 'jpeg' => 'file-earmark-image text-info', 'zip' => 'file-earmark-zip text-secondary', 'txt' => 'file-earmark-text'];
$statutC = ['brouillon' => 'secondary', 'depose' => 'primary', 'en_validation' => 'warning', 'valide' => 'success', 'rejete' => 'danger',
    'signe' => 'success', 'publie' => 'info', 'archive' => 'dark', 'expire' => 'warning', 'supprime' => 'danger'];
$niveauC = ['public' => 'success', 'interne' => 'secondary', 'restreint' => 'warning', 'confidentiel' => 'danger', 'tres_confidentiel' => 'danger'];
$taille = fn ($o) => $o >= 1048576 ? number_format($o / 1048576, 1, ',', ' ').' Mo' : max(1, (int) round($o / 1024)).' Ko';
$estPaginateur = method_exists($documents, 'links');
@endphp
<div class="card border-0 shadow-sm">
    @if($estPaginateur && method_exists($documents, 'total'))
    <div class="card-header bg-white d-flex justify-content-between align-items-center small">
        <span>{{ $documents->total() }} document(s)@if($documents->total() > $documents->count()) · {{ $documents->firstItem() }}–{{ $documents->lastItem() }}@endif</span>
        @if(! empty($parPage))
        <span class="d-flex align-items-center gap-1">Afficher
            @foreach([20, 50, 100] as $n)
                <a href="{{ request()->fullUrlWithQuery(['par_page' => $n, 'page' => null]) }}" class="badge text-decoration-none {{ $documents->perPage() === $n ? 'bg-primary' : 'bg-light text-dark border' }}">{{ $n }}</a>
            @endforeach
        </span>
        @endif
    </div>
    @endif
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light small">
                <tr>
                    <th>Document</th>
                    <th>Classement</th>
                    <th>Origine</th>
                    <th>Statut</th>
                    <th>Confidentialité</th>
                    <th>Dépôt</th>
                    <th class="text-end">Taille</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($documents as $doc)
                @php
                    $ext = strtolower((string) $doc->extension);
                    $statut = $doc->statut?->value;
                    $niveau = $doc->niveau_confidentialite?->value;
                    $expire = $doc->date_expiration && $doc->date_expiration->isPast();
                @endphp
                <tr>
                    <td style="max-width: 340px">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-{{ $icones[$ext] ?? 'file-earmark' }} fs-4 lh-1"></i>
                            <div class="min-w-0">
                                <a href="{{ route('ged.documents.show', $doc) }}" class="fw-semibold text-decoration-none d-block text-truncate" title="{{ $doc->titre }}">{{ $doc->titre }}</a>
                                <div class="small text-muted">
                                    <span class="font-monospace">{{ $doc->reference }}</span>
                                    @if(($doc->version_courante ?? 1) > 1) · v{{ $doc->version_courante }}@endif
                                    @if($doc->est_signe)<i class="bi bi-patch-check-fill text-success ms-1" title="Signé"></i>@endif
                                    @if($doc->est_verrouille)<i class="bi bi-lock-fill text-secondary ms-1" title="Verrouillé"></i>@endif
                                    @if($doc->est_archive)<i class="bi bi-archive-fill text-dark ms-1" title="Archivé"></i>@endif
                                </div>
                                @if($doc->tags->isNotEmpty())
                                    <div>@foreach($doc->tags->take(3) as $tag)<span class="badge bg-light text-dark border me-1" style="font-size: .65rem">{{ $tag->nom }}</span>@endforeach</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="small">
                        <div>{{ $doc->typeDocument?->libelle ?? '—' }}</div>
                        <div class="text-muted">{{ $doc->categorie?->libelle }}@if($doc->dossier) · {{ \Illuminate\Support\Str::limit($doc->dossier->titre, 25) }}@endif</div>
                    </td>
                    <td class="small">
                        @if($doc->module_source)<span class="badge bg-light text-dark border">{{ $doc->module_source }}</span>@else<span class="text-muted">Dépôt direct</span>@endif
                    </td>
                    <td><span class="badge bg-{{ $statutC[$statut] ?? 'secondary' }}-subtle text-{{ $statutC[$statut] ?? 'secondary' }}-emphasis">{{ $doc->statut?->libelle() }}</span></td>
                    <td><span class="badge bg-{{ $niveauC[$niveau] ?? 'secondary' }}-subtle text-{{ $niveauC[$niveau] ?? 'secondary' }}-emphasis">{{ $doc->niveau_confidentialite?->libelle() }}</span></td>
                    <td class="small text-nowrap">
                        {{ $doc->date_depot?->format('d/m/Y') }}
                        <div class="text-muted">{{ $doc->createur?->name }}</div>
                        @if($doc->date_expiration)<div class="{{ $expire ? 'text-danger' : 'text-muted' }}">exp. {{ $doc->date_expiration->format('d/m/Y') }}</div>@endif
                    </td>
                    <td class="small text-end text-nowrap">{{ $taille((int) $doc->taille) }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('ged.documents.show', $doc) }}" class="btn btn-sm btn-outline-primary" title="Ouvrir"><i class="bi bi-eye"></i></a>
                        @can('download', $doc)
                        @if(in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true))
                        <a href="{{ route('ged.documents.preview', $doc) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Aperçu"><i class="bi bi-zoom-in"></i></a>
                        @endif
                        <a href="{{ route('ged.documents.download', $doc) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger"><i class="bi bi-download"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">
                    Aucun document trouvé.
                    @if(request()->query())<a href="{{ url()->current() }}">Réinitialiser les filtres</a>@endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($estPaginateur)
    <div class="card-footer bg-white">{{ $documents->links() }}</div>
    @endif
</div>
