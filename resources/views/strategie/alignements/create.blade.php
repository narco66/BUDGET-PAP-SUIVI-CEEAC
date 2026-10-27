@extends('layouts.app')
@section('titre', 'Nouvel alignement')
@section('contenu')
@include('strategie._nav', ['active' => 'alignements'])
<div class="card border-0 shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('strategie.alignements.store') }}">@csrf
        <div class="row g-3">
            <x-form.select name="vision_id" label="Vision" col="4" :value="old('vision_id')" placeholder="—">
                @foreach($visions as $v)
                    <option value="{{ $v->id }}" @selected(old('vision_id')==$v->id)>{{ $v->reference }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="plan_strategique_id" label="Plan" col="4" :value="old('plan_strategique_id')" placeholder="—">
                @foreach($plans as $p)
                    <option value="{{ $p->id }}" @selected(old('plan_strategique_id')==$p->id)>{{ $p->reference }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="orientation_strategique_id" label="Orientation" col="4" :value="old('orientation_strategique_id')" placeholder="—">
                @foreach($orientations as $o)
                    <option value="{{ $o->id }}" @selected(old('orientation_strategique_id')==$o->id)>{{ $o->code }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="alignable_type" label="Type entité" col="4" id="alignable_type" :value="old('alignable_type', \App\Models\Strategie\ProgrammeStrategique::class)" required
                :options="[
                    \App\Models\Strategie\ProgrammeStrategique::class => 'Programme stratégique',
                    \App\Models\Strategie\ProjetStrategique::class => 'Projet',
                    \App\Models\Activite::class => 'Activité RBM',
                ]" />
            <div class="col-md-4">
                <label for="alignable_id" class="form-label">Entité <span class="text-danger">*</span></label>
                <select name="alignable_id" id="alignable_id" class="form-select @error('alignable_id') is-invalid @enderror" required>
                    @foreach($programmes as $pg)
                        <option value="{{ $pg->id }}" data-type="{{ \App\Models\Strategie\ProgrammeStrategique::class }}" @selected(old('alignable_id')==$pg->id)>{{ $pg->code }} (programme)</option>
                    @endforeach
                    @foreach($projets as $pr)
                        <option value="{{ $pr->id }}" data-type="{{ \App\Models\Strategie\ProjetStrategique::class }}" @selected(old('alignable_id')==$pr->id)>{{ $pr->code }} (projet)</option>
                    @endforeach
                    @foreach($activites as $ac)
                        <option value="{{ $ac->id }}" data-type="{{ \App\Models\Activite::class }}" @selected(old('alignable_id')==$ac->id)>{{ $ac->code }} (activité)</option>
                    @endforeach
                </select>
                @error('alignable_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <x-form.select name="niveau_alignement" label="Niveau" col="4" :value="old('niveau_alignement', 'direct')" required
                :options="['direct'=>'Direct','indirect'=>'Indirect','partiel'=>'Partiel']" />
            <x-form.textarea name="commentaire" label="Commentaire" col="12" :value="old('commentaire')" :rows="2" />
        </div>
        <x-form.actions :cancel-url="route('strategie.alignements.index')" />
    </form>
</div></div>
@endsection

@push('scripts')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.getElementById('alignable_type')?.addEventListener('change', function () {
    const type = this.value;
    document.querySelectorAll('#alignable_id option').forEach(opt => {
        if (!opt.value) return;
        opt.hidden = opt.dataset.type !== type;
    });
});
document.getElementById('alignable_type')?.dispatchEvent(new Event('change'));
</script>
@endpush
