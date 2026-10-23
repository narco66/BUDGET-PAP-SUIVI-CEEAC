@extends('layouts.app')
@section('titre', 'Périmètres d\'accès · '.$utilisateur->name)

@section('contenu')
<div class="card border-0 shadow-sm" style="max-width:900px;"><div class="card-body">
    <p class="text-muted small">Sans périmètre défini, l'utilisateur dispose d'un accès global. Les types « département / direction / service » couvrent la structure ciblée et ses sous-structures.</p>

    <form action="{{ route('utilisateurs.scopes.update', $utilisateur) }}" method="POST"
          x-data="{ lignes: {{ \Illuminate\Support\Js::from($utilisateur->accessScopes->map(fn($s)=>['type'=>$s->type,'scope_id'=>$s->scope_id])) }} }">
        @method('PUT') @csrf
        <table class="table align-middle">
            <thead class="table-light"><tr><th style="width:40%">Type de périmètre</th><th>Cible</th><th></th></tr></thead>
            <tbody>
                <template x-for="(l, i) in lignes" :key="i">
                    <tr>
                        <td>
                            <select :name="`scopes[${i}][type]`" x-model="l.type" class="form-select form-select-sm">
                                @foreach (['global'=>'Accès global','departement'=>'Département','direction'=>'Direction','service'=>'Service','programme'=>'Programme','exercice'=>'Exercice','lecture_seule'=>'Lecture seule','propres_dossiers'=>'Ses propres dossiers','a_valider'=>'Dossiers à valider'] as $k=>$v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select :name="`scopes[${i}][scope_id]`" x-model="l.scope_id" class="form-select form-select-sm"
                                    :disabled="['global','lecture_seule','propres_dossiers','a_valider'].includes(l.type)">
                                <option value="">—</option>
                                <optgroup label="Structures">
                                    @foreach ($structures as $s)<option value="{{ $s->id }}">{{ $s->code }} · {{ $s->libelle }}</option>@endforeach
                                </optgroup>
                                <optgroup label="Programmes">
                                    @foreach ($programmes as $p)<option value="{{ $p->id }}">{{ $p->code }} · {{ $p->intitule }}</option>@endforeach
                                </optgroup>
                                <optgroup label="Exercices">
                                    @foreach ($exercices as $e)<option value="{{ $e->id }}">Exercice {{ $e->annee }}</option>@endforeach
                                </optgroup>
                            </select>
                        </td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" @click="lignes.splice(i,1)"><i class="bi bi-x"></i></button></td>
                    </tr>
                </template>
            </tbody>
        </table>
        <button type="button" class="btn btn-sm btn-outline-success" @click="lignes.push({type:'global',scope_id:''})"><i class="bi bi-plus-lg me-1"></i>Ajouter un périmètre</button>
        <div class="mt-3">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
            <a href="{{ route('utilisateurs.show', $utilisateur) }}" class="btn btn-light">Annuler</a>
        </div>
    </form>
</div></div>
@endsection
