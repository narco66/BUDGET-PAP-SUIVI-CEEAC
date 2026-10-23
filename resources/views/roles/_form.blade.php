@csrf
@php $r = $role ?? null; $permsRole = old('permissions', isset($r) ? $r->permissions->pluck('name')->all() : []); @endphp
<div class="row g-3">
    <x-form.input name="name" label="Code" col="4" :value="$r->name ?? ''" required
        :readonly="$r && $r->type==='systeme'"
        help="minuscules, chiffres, underscore" />
    <x-form.input name="libelle" label="Libellé" col="5" :value="$r->libelle ?? ''" required />
    <x-form.select name="type" label="Type" col="3" :value="$r->type ?? 'metier'"
        :disabled="$r && $r->protege && !auth()->user()->estAdministrateur()"
        :options="['metier'=>'Métier','systeme'=>'Système']" />
    <x-form.textarea name="description" label="Description" col="12" :value="$r->description ?? ''" :rows="2" />
</div>

<h6 class="fw-bold mt-3"><i class="bi bi-grid-3x3-gap me-1"></i>Matrice des permissions</h6>
<div class="border rounded p-2" style="max-height:420px;overflow:auto;">
    @foreach ($permissionsParModule as $module => $perms)
        <div class="mb-2">
            <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                <strong class="small text-primary">{{ $module }}</strong>
                <a href="#" class="small" onclick="event.preventDefault();this.closest('.mb-2').querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=true)">tout</a>
            </div>
            <div class="row">
                @foreach ($perms as $perm)
                    <div class="col-md-3"><div class="form-check">
                        <input type="checkbox" name="permissions[]" value="{{ $perm->name }}" id="p_{{ $perm->id }}" class="form-check-input @error('permissions') is-invalid @enderror @error('permissions.*') is-invalid @enderror" @checked(in_array($perm->name, $permsRole, true))>
                        <label for="p_{{ $perm->id }}" class="form-check-label small">{{ $perm->action }}</label>
                    </div></div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@error('permissions')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
@error('permissions.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
<x-form.actions :cancel-url="route('roles.index')" />
