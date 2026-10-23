@extends('layouts.app')

@section('titre', 'Paramètres GED')

@section('contenu')

<h4 class="fw-bold mb-3">Paramètres du module GED</h4>

<div class="row g-3">

    <div class="col-md-6">

        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white fw-bold">Stockage</div>

            <div class="card-body small">

                <p>Emplacement : <code>storage/app/private/ged/{année}/{mois}/{module}/{type}/</code></p>

                <p>Taille max par défaut : {{ config('ged.taille_max_mo', 10) }} Mo</p>

                <p>Extensions : PDF, images, Office, CSV, TXT</p>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">

                <span><i class="bi bi-shield-virus me-1"></i>Antivirus (ClamAV)</span>

                @if($antivirus['protection_effective'])

                    <span class="badge bg-success">Actif</span>

                @elseif($antivirus['actif'])

                    <span class="badge bg-warning text-dark">Configuré — scanner indisponible</span>

                @else

                    <span class="badge bg-secondary">Inactif</span>

                @endif

            </div>

            <div class="card-body small">

                <dl class="mb-2">

                    <dt class="text-muted">Driver</dt><dd class="font-monospace">{{ $antivirus['driver'] }}</dd>

                    <dt class="text-muted">Protection effective</dt><dd>{{ $antivirus['protection_effective'] ? 'Oui' : 'Non' }}</dd>

                    @if($antivirus['actif'] && $antivirus['driver'] === 'clamav')

                    <dt class="text-muted">ClamAV disponible</dt><dd>{{ $antivirus['scanner_disponible'] ? 'Oui' : 'Non — vérifier clamscan' }}</dd>

                    <dt class="text-muted">Binaire</dt><dd class="font-monospace">{{ $antivirus['clamscan_path'] }}</dd>

                    @endif

                    <dt class="text-muted">Quarantaine</dt><dd>{{ $antivirus['quarantaine'] ? 'ged/quarantine/' : 'Suppression directe' }}</dd>

                </dl>

                <p class="text-muted mb-0">Production : <code>GED_ANTIVIRUS_ACTIF=true</code> · <code>GED_ANTIVIRUS_DRIVER=clamav</code></p>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">

                <span><i class="bi bi-pen-fill me-1"></i>Signature électronique (eIDAS)</span>

                @if($signature['qualification_eidas'] && $signature['prestataire_disponible'])

                    <span class="badge bg-success">Qualifiée</span>

                @elseif($signature['provider'] === 'interne')

                    <span class="badge bg-secondary">Interne</span>

                @else

                    <span class="badge bg-warning text-dark">Externe — non configuré</span>

                @endif

            </div>

            <div class="card-body small">

                <dl class="mb-2">

                    <dt class="text-muted">Provider</dt><dd class="font-monospace">{{ $signature['provider'] }}</dd>

                    <dt class="text-muted">Niveau</dt><dd>{{ $signature['niveau_libelle'] }}</dd>

                    @if($signature['provider'] === 'externe')

                    <dt class="text-muted">API configurée</dt><dd>{{ $signature['api_url_configuree'] ? 'Oui' : 'Non' }}</dd>

                    @endif

                    <dt class="text-muted">Qualification eIDAS</dt><dd>{{ $signature['qualification_eidas'] ? 'Oui' : 'Non' }}</dd>

                </dl>

                <p class="text-muted mb-0">Production : <code>GED_SIGNATURE_PROVIDER=externe</code> · <code>GED_SIGNATURE_NIVEAU=qualifiee</code></p>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white fw-bold">Sécurité & conformité</div>

            <div class="card-body small">

                <ul class="mb-0">

                    <li>Hash SHA-256 systématique</li>

                    <li>Analyse antivirus à l'upload (ClamAV)</li>

                    <li>Signature qualifiée eIDAS (prestataire externe ou test CI)</li>

                    <li>Journalisation complète des accès</li>

                    <li>Archivage probant avec verrouillage</li>

                </ul>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white fw-bold">Planification automatique</div>

            <div class="card-body small">

                <ul class="mb-2">

                    <li><code>ged:expiration-alerte</code> — quotidien 07:00</li>

                    <li><code>ged:ocr-traiter</code> — quotidien 02:30</li>

                    <li><code>ged:conservation-appliquer</code> — lundi 06:00</li>

                </ul>

                <p class="mb-0 text-muted">Laragon : planifier <code>php artisan schedule:run</code> chaque minute.</p>

            </div>

        </div>

    </div>

</div>

@endsection

