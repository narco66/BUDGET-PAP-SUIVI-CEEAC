@extends('layouts.app')
@section('titre', ($nc->exists ? 'Modifier' : 'Nouvelle').' non-conformité')
@php
    $val = fn ($k, $d = null) => old($k, $nc->$k ?? $d);
    $date = fn ($k) => ($v = $val($k)) instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
@endphp

@section('contenu')
<a href="{{ route('conformite.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Non-conformités</a>
<h4 class="fw-bold mb-3">{{ $nc->exists ? 'Modifier '.$nc->code : 'Nouvelle non-conformité' }}</h4>

<form method="POST" action="{{ $nc->exists ? route('conformite.update', $nc) : route('conformite.store') }}">
    @csrf @if($nc->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.input name="libelle" label="Libellé de l'écart" col="8" :value="$val('libelle')" required maxlength="255" />
            <x-form.select name="gravite" label="Gravité" col="4" :value="$val('gravite')" required :options="$gravites" :placeholder="false" />
            <x-form.select name="source" label="Source" col="4" :value="$val('source')" required :options="$sources" :placeholder="false" />
            <x-form.select name="controle_interne_id" label="Contrôle interne lié" col="4" :value="$val('controle_interne_id')" placeholder="— Aucun —">
                @foreach ($controles as $c)
                    <option value="{{ $c->id }}" @selected($val('controle_interne_id') == $c->id)>{{ $c->code }} · {{ \Illuminate\Support\Str::limit($c->libelle, 30) }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="mission_audit_id" label="Mission d'audit liée" col="4" :value="$val('mission_audit_id')" placeholder="— Aucune —">
                @foreach ($missions as $m)
                    <option value="{{ $m->id }}" @selected($val('mission_audit_id') == $m->id)>{{ $m->numero }} · {{ \Illuminate\Support\Str::limit($m->libelle, 26) }}</option>
                @endforeach
            </x-form.select>
            <x-form.textarea name="description" label="Description de l'écart" col="12" :value="$val('description')" :rows="2" />
            <x-form.input name="processus" label="Processus / domaine" col="4" :value="$val('processus')" maxlength="150" />
            <x-form.select name="structure_id" label="Structure" col="4" :value="$val('structure_id')" placeholder="— Aucune —">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->code }} · {{ $s->sigle ?: $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="date_detection" label="Date de détection" type="date" col="4" :value="$date('date_detection')" />

            <div class="col-12"><hr class="my-1"><span class="small text-muted fw-semibold">Plan de remédiation</span></div>
            <x-form.textarea name="plan_action" label="Action de remédiation" col="12" :value="$val('plan_action')" :rows="2" />
            <x-form.select name="responsable_user_id" label="Responsable" col="4" :value="$val('responsable_user_id')" placeholder="— Aucun —">
                @foreach ($utilisateurs as $u)
                    <option value="{{ $u->id }}" @selected($val('responsable_user_id') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="echeance" label="Échéance" type="date" col="3" :value="$date('echeance')" />
            <x-form.select name="statut" label="Statut" col="3" :value="$val('statut')" required :options="$statuts" :placeholder="false" />
            <x-form.input name="taux_avancement" label="Avancement %" type="number" col="2" min="0" max="100" :value="$val('taux_avancement', 0)" />
        </div>
        <x-form.footer :cancel-url="route('conformite.index')" />
    </div>
</form>
@endsection
