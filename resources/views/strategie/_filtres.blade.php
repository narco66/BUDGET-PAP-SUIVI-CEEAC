<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-md-4">
        <label class="form-label small mb-0">Recherche</label>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Référence, code, intitulé…">
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-0">Statut</label>
        <select name="statut" class="form-select form-select-sm">
            <option value="">— Tous —</option>
            @foreach ($statuts ?? ['actif', 'brouillon', 'archive'] as $s)
                <option value="{{ $s }}" @selected(request('statut') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search me-1"></i>Filtrer</button>
        <a href="{{ url()->current() }}" class="btn btn-sm btn-light">Réinitialiser</a>
    </div>
</form>
