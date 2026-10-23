@extends('layouts.app')
@section('titre', 'Import RBM/GAR')

@section('contenu')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 mb-0"><i class="bi bi-file-earmark-arrow-up me-2"></i>Imports Excel — Module RBM/GAR</h1>
            <a href="{{ route('rbm.hub') }}" class="btn btn-sm btn-outline-secondary">Retour au hub</a>
        </div>

        @if(session('rejets'))
        <div class="alert alert-warning">
            <strong>Avertissements / rejets :</strong>
            <ul class="mb-0 small">@foreach(session('rejets') as $r)<li>{{ $r }}</li>@endforeach</ul>
        </div>
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">1. Chaîne RBM (Pilier → Activité)</div>
                    <div class="card-body">
                        <a href="{{ route('rbm.import.modele') }}" class="btn btn-sm btn-outline-success mb-3"><i class="bi bi-download"></i> Modèle chaîne</a>
                        <form method="POST" action="{{ route('rbm.import.store') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">Exercice</label>
                                <select name="exercice_id" class="form-select form-select-sm" required>
                                    @foreach($exercices as $ex)
                                        <option value="{{ $ex->id }}" @selected(old('exercice_id', $exercice?->id)===$ex->id)>{{ $ex->annee }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Fichier</label>
                                <input type="file" name="fichier" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required>
                            </div>
                            <button class="btn btn-primary btn-sm">Importer la chaîne</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">2. Indicateurs (IOV)</div>
                    <div class="card-body">
                        <a href="{{ route('rbm.import.modele-indicateurs') }}" class="btn btn-sm btn-outline-success mb-3"><i class="bi bi-download"></i> Modèle indicateurs</a>
                        <form method="POST" action="{{ route('rbm.import.indicateurs') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">Exercice</label>
                                <select name="exercice_id_indicateurs" class="form-select form-select-sm" required>
                                    @foreach($exercices as $ex)
                                        <option value="{{ $ex->id }}">{{ $ex->annee }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Fichier</label>
                                <input type="file" name="fichier_indicateurs" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required>
                            </div>
                            <button class="btn btn-primary btn-sm">Importer les indicateurs</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
