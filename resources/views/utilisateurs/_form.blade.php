@csrf
@php $u = $utilisateur ?? null; @endphp
<div class="row g-3">
    <x-form.input name="name" label="Nom complet" col="4" :value="$u->name ?? ''" required />
    <x-form.input name="email" label="E-mail" type="email" col="4" :value="$u->email ?? ''" required />
    <x-form.input name="matricule" label="Matricule" col="4" :value="$u->matricule ?? ''" />

    <x-form.input name="password" label="Mot de passe" type="password" col="4" autocomplete="new-password"
        :help="$u ? null : '(vide = temporaire généré)'" />
    <x-form.input name="password_confirmation" label="Confirmation" type="password" col="4" autocomplete="new-password" />
    <x-form.select name="statut" label="Statut" col="4" :value="$u->statut ?? 'actif'" required
        :options="['actif'=>'Actif','suspendu'=>'Suspendu','desactive'=>'Désactivé']" />

    <x-form.input name="telephone" label="Téléphone" col="3" :value="$u->telephone ?? ''" />
    <x-form.select name="fonction_id" label="Fonction" col="3" :value="$u->fonction_id ?? ''" placeholder="—">
        @foreach ($fonctions as $f)
            <option value="{{ $f->id }}" @selected(old('fonction_id', $u->fonction_id ?? '')==$f->id)>{{ $f->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="poste_id" label="Poste" col="3" :value="$u->poste_id ?? ''" placeholder="—">
        @foreach ($postes as $p)
            <option value="{{ $p->id }}" @selected(old('poste_id', $u->poste_id ?? '')==$p->id)>{{ $p->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.file name="avatar" label="Photo / avatar" col="3" accept="image/*" />

    <x-form.select name="structure_id" label="Structure principale" col="4" :value="$u->structure_id ?? ''" placeholder="—">
        @foreach ($structures as $s)
            <option value="{{ $s->id }}" @selected(old('structure_id', $u->structure_id ?? '')==$s->id)>{{ $s->code }} · {{ $s->libelle }}</option>
        @endforeach
    </x-form.select>
    <div class="col-md-8">
        <label class="form-label">Rattachements supplémentaires</label>
        <div class="row @error('structures') border border-danger rounded @enderror @error('structures.*') border border-danger rounded @enderror" style="max-height:120px;overflow:auto;">
            @php $structAttach = old('structures', isset($u) ? $u->structures->pluck('id')->all() : []); @endphp
            @foreach ($structures as $s)
                <div class="col-md-4"><div class="form-check">
                    <input type="checkbox" name="structures[]" value="{{ $s->id }}" id="st{{ $s->id }}" class="form-check-input" @checked(collect($structAttach)->contains($s->id))>
                    <label for="st{{ $s->id }}" class="form-check-label small">{{ $s->sigle ?? $s->libelle }}</label>
                </div></div>
            @endforeach
        </div>
        @error('structures')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @error('structures.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-7">
        <label class="form-label">Rôles (RBAC)</label>
        @php $rolesUser = old('roles', isset($u) ? $u->getRoleNames()->all() : []); @endphp
        <div class="row border rounded p-2 @error('roles') border-danger @enderror @error('roles.*') border-danger @enderror" style="max-height:200px;overflow:auto;">
            @foreach ($roles as $role)
                <div class="col-md-6"><div class="form-check">
                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" id="r{{ $role->id }}" class="form-check-input" @checked(collect($rolesUser)->contains($role->name))>
                    <label for="r{{ $role->id }}" class="form-check-label small">
                        {{ $role->libelle ?? str_replace('_',' ',$role->name) }}
                        @if($role->protege)<i class="bi bi-shield-lock text-warning" title="Rôle protégé"></i>@endif
                    </label>
                </div></div>
            @endforeach
        </div>
        @error('roles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @error('roles.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        <small class="text-muted">Les rôles protégés ne sont attribuables que par un administrateur.</small>
    </div>
    <div class="col-md-5">
        <label class="form-label">Profils fonctionnels</label>
        @php $profilsUser = old('profils', isset($u) ? $u->profils->pluck('id')->all() : []); @endphp
        <div class="row border rounded p-2 @error('profils') border-danger @enderror @error('profils.*') border-danger @enderror" style="max-height:200px;overflow:auto;">
            @foreach ($profils as $prof)
                <div class="col-12"><div class="form-check">
                    <input type="checkbox" name="profils[]" value="{{ $prof->id }}" id="pf{{ $prof->id }}" class="form-check-input" @checked(collect($profilsUser)->contains($prof->id))>
                    <label for="pf{{ $prof->id }}" class="form-check-label small">{{ $prof->libelle }}</label>
                </div></div>
            @endforeach
        </div>
        @error('profils')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @error('profils.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
</div>
<x-form.actions :cancel-url="route('utilisateurs.index')" />
