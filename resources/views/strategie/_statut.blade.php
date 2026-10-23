@php
    $classes = [
        'actif' => 'success', 'brouillon' => 'secondary', 'archive' => 'dark', 'obsolete' => 'warning',
        'en_validation' => 'warning', 'rejete' => 'danger', 'publie' => 'success', 'en_revision' => 'info',
        'inactif' => 'secondary', 'planifie' => 'info', 'en_cours' => 'primary', 'suspendu' => 'warning',
        'acheve' => 'success', 'identifie' => 'danger', 'en_traitement' => 'warning', 'mitige' => 'info', 'cloture' => 'success',
    ];
    $cls = $classes[$statut] ?? 'secondary';
@endphp
<span class="badge bg-{{ $cls }}">{{ ucfirst(str_replace('_', ' ', $statut)) }}</span>
