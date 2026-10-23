@csrf
@php $p = $profil ?? null; $rolesProfil = old('roles', isset($p) ? $p->roles->pluck('id')->all() : []); @endphp
<div class="row g-3">
    <x-form.input name="code" label="Code" col="3" :value="$p->code ?? ''" required />
    <x-form.input name="libelle" label="Libellé" col="5" :value="$p->libelle ?? ''" required />
    <x-form.select name="type" label="Type" col="4" :value="$p->type ?? ''" required
        :options="['institutionnel'=>'Institutionnel','metier'=>'Métier','budgetaire'=>'Budgétaire','pap_gar'=>'PAP / GAR','validation'=>'Validation','consultation'=>'Consultation','audit'=>'Audit']" />
    <div class="col-12">
        <label class="form-label">Rôles inclus dans ce profil</label>
        <div class="row border rounded p-2 @error('roles') border-danger @enderror @error('roles.*') border-danger @enderror">
            @foreach ($roles as $role)
                <div class="col-md-4"><div class="form-check">
                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" id="rp{{ $role->id }}" class="form-check-input" @checked(collect($rolesProfil)->contains($role->id))>
                    <label for="rp{{ $role->id }}" class="form-check-label small">{{ $role->libelle ?? $role->name }}</label>
                </div></div>
            @endforeach
        </div>
        @error('roles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @error('roles.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
    <x-form.checkbox name="actif" label="Profil actif" col="12" :checked="$p->actif ?? true" />
</div>
<x-form.actions :cancel-url="route('profils.index')" />
