@php
    $couleurGravite = ['information' => 'secondary', 'faible' => 'info', 'modere' => 'warning', 'eleve' => 'danger', 'critique' => 'danger'];
    $couleurResultat = ['succes' => 'success', 'refus' => 'danger', 'echec' => 'danger'];
@endphp
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>Horodatage <span class="text-muted fw-normal small">({{ \App\Support\Horodatage::fuseau() }})</span></th><th>Catégorie</th><th>Module</th><th>Événement</th>
                <th>Auteur</th><th>Objet</th><th>Résultat</th><th>Gravité</th><th class="text-center" title="Empreinte SHA-256">Intégrité</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($journaux as $j)
                @php $valide = $j->empreinteValide(); @endphp
                <tr @class(['table-danger' => $j->action === 'sod_violation' || $valide === false])>
                    <td class="small text-nowrap" title="{{ \App\Support\Horodatage::utc($j->instant()) }}">{{ \App\Support\Horodatage::local($j->instant()) }}</td>
                    @if ($j->categorie)
                        <td class="small">{{ \App\Models\JournalAudit::CATEGORIES[$j->categorie] ?? $j->categorie }}</td>
                        <td><span class="badge text-bg-light border">{{ $j->module ?? '—' }}</span></td>
                    @else
                        {{-- Événement antérieur au module : classement déduit à l'affichage, la ligne n'est pas réécrite. --}}
                        <td class="small fst-italic text-muted" title="Déduit (événement antérieur au module)">{{ \App\Models\JournalAudit::CATEGORIES[\App\Support\AuditLogger::categorie($j->action)] }}</td>
                        <td><span class="badge text-bg-light border fst-italic text-muted" title="Déduit (événement antérieur au module)">{{ \App\Support\AuditLogger::moduleDuType($j->auditable_type) ?? \App\Support\AuditLogger::module($j->action, null) }}</span></td>
                    @endif
                    <td class="small"><code class="small">{{ $j->code_evenement ?? strtoupper($j->action) }}</code></td>
                    <td class="small">{{ $j->user_nom ?? 'Système' }}
                        @if ($j->pour_compte_de)<div class="text-primary small"><i class="bi bi-person-badge"></i> pour {{ $j->titulaire?->name ?? '#'.$j->pour_compte_de }}</div>@endif
                        @if ($j->role)<div class="text-muted small">{{ $j->role }}</div>@endif</td>
                    <td class="small">{{ $j->libelleObjet() ?? '—' }}</td>
                    <td>@if ($j->resultat)<span class="badge text-bg-{{ $couleurResultat[$j->resultat] ?? 'secondary' }}">{{ \App\Models\JournalAudit::RESULTATS[$j->resultat] ?? $j->resultat }}</span>@else — @endif</td>
                    <td>@if ($j->gravite)<span class="badge text-bg-{{ $couleurGravite[$j->gravite] ?? 'secondary' }}">{{ \App\Models\JournalAudit::GRAVITES[$j->gravite] ?? $j->gravite }}</span>@else — @endif</td>
                    <td class="text-center">
                        @if ($valide === true)<i class="bi bi-shield-check text-success" title="Empreinte conforme"></i>
                        @elseif ($valide === false)<i class="bi bi-shield-exclamation text-danger" title="Contenu modifié après inscription"></i>
                        @else<i class="bi bi-dash text-muted" title="Événement antérieur au module (couvert par le scellement)"></i>@endif
                    </td>
                    <td class="text-end"><a href="{{ route('audit.show', $j) }}" class="btn btn-sm btn-outline-primary" title="Détail"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">Aucun événement dans votre périmètre pour ces critères.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $journaux->links() }}</div>
