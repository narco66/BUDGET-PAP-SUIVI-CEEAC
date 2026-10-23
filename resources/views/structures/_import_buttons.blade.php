@if(\App\Support\ReferentielAdministratifAuthorization::peut(auth()->user(), 'importer'))

<div class="btn-group btn-group-sm">

    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">

        <i class="bi bi-upload me-1"></i>Importer

    </button>

    <ul class="dropdown-menu dropdown-menu-end">

        <li>

            <a class="dropdown-item" href="{{ route('structure.importer.modele') }}">

                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Télécharger le modèle Excel

            </a>

        </li>

        <li><hr class="dropdown-divider"></li>

        <li class="px-3 py-2">

            <form action="{{ route('structure.importer.structures') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-2">

                @csrf

                <input type="file" name="fichier" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required>

                <button type="submit" class="btn btn-sm btn-primary">Importer structures</button>

            </form>

        </li>

    </ul>

</div>

@endif

