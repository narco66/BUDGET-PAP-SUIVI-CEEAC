@can('structure.exporter')
@php
    $exportQuery = array_filter([
        'type' => request('type'),
        'q' => request('q'),
    ]);
@endphp
<div class="btn-group btn-group-sm">
    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download me-1"></i>Exporter
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
            <a class="dropdown-item" href="{{ route('structure.exporter.annuaire.excel', $exportQuery) }}">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Annuaire Excel
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('structure.exporter.annuaire.pdf', $exportQuery) }}">
                <i class="bi bi-file-earmark-pdf me-2"></i>Annuaire PDF
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><h6 class="dropdown-header"><i class="bi bi-shield-check me-1"></i>Archiver (officiel)</h6></li>
        <li>
            <a class="dropdown-item" href="{{ route('structure.exporter.annuaire.archiver', array_merge(['format' => 'excel'], $exportQuery)) }}">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Annuaire Excel
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('structure.exporter.annuaire.archiver', array_merge(['format' => 'pdf'], $exportQuery)) }}">
                <i class="bi bi-file-earmark-pdf me-2"></i>Annuaire PDF
            </a>
        </li>
    </ul>
</div>
@elsecan('administratif.exporter')
@php
    $exportQuery = array_filter([
        'type' => request('type'),
        'q' => request('q'),
    ]);
@endphp
<div class="btn-group btn-group-sm">
    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download me-1"></i>Exporter
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
            <a class="dropdown-item" href="{{ route('structure.exporter.annuaire.excel', $exportQuery) }}">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Annuaire Excel
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('structure.exporter.annuaire.pdf', $exportQuery) }}">
                <i class="bi bi-file-earmark-pdf me-2"></i>Annuaire PDF
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><h6 class="dropdown-header"><i class="bi bi-shield-check me-1"></i>Archiver (officiel)</h6></li>
        <li>
            <a class="dropdown-item" href="{{ route('structure.exporter.annuaire.archiver', array_merge(['format' => 'excel'], $exportQuery)) }}">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Annuaire Excel
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('structure.exporter.annuaire.archiver', array_merge(['format' => 'pdf'], $exportQuery)) }}">
                <i class="bi bi-file-earmark-pdf me-2"></i>Annuaire PDF
            </a>
        </li>
    </ul>
</div>
@endcan
