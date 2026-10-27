@extends('layouts.app')
@section('titre', 'Chaîne RBM · Exercice '.$exercice->annee)

@push('styles')
<style>
    .rbm-niveau { border-left:2px solid #e3e8ef; margin-left:.5rem; padding-left:1rem; }
    .rbm-item { padding:.35rem .5rem; border-radius:.4rem; }
    .rbm-item:hover { background:#f1f5fb; }
    .rbm-item.rbm-archive { opacity:.75; background:#f8f9fa; }
    .rbm-code { font-family:monospace; font-size:.8rem; }
    .lvl-pilier{border-left-color:#0a4a8f}.lvl-axe{border-left-color:#198754}.lvl-produit{border-left-color:#fd7e14}
    .lvl-sous_produit{border-left-color:#6f42c1}.lvl-activite{border-left-color:#0dcaf0}.lvl-tache{border-left-color:#adb5bd}
    .montant { font-variant-numeric:tabular-nums; }
</style>
@endpush

@php
use App\Support\RbmStatutFiltre;
$ex = $exercice->id;
$fmt = fn ($n) => number_format((float)$n, 0, ',', ' ');
$statutFiltre = $statutFiltre ?? 'actif';
// Droits identiques pour tous les nœuds : évalués une fois (chaque contrôle coûtait ~1 ms × 580 nœuds).
$droitsRbm = [
    'indicateurs' => auth()->user()->can('indicateur.voir'),
    'historique' => auth()->user()->canAny(['rbm.consulter_journal', 'audit.voir']),
    'ged' => auth()->user()->can('ged.voir'),
];
@endphp

@section('contenu')
@include('rbm._nav-module', ['exercice' => $exercice, 'exercices' => $exercices, 'page' => 'arbre', 'statutFiltre' => $statutFiltre])

@can('rbm.action', ['pilier', 'creer'])
<div class="mb-3">
    <a href="{{ route('rbm.create', 'pilier') }}?parent={{ $ex }}&exercice={{ $ex }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Ajouter un pilier</a>
</div>
@endcan

<div class="card border-0 shadow-sm"><div class="card-body">
@forelse ($exercice->piliers as $pilier)
    @if(RbmStatutFiltre::visible($pilier, 'pilier', $statutFiltre))
    @php $totPilier = $pilier->axes->flatMap->produits->flatMap->sousProduits->flatMap->activites->sum('montant_total'); @endphp
    <div class="rbm-item lvl-pilier d-flex justify-content-between align-items-center @if(RbmStatutFiltre::statut($pilier, 'pilier')==='archive') rbm-archive @endif">
        <div><span class="badge bg-primary">PILIER</span> <span class="rbm-code">{{ $pilier->code }}</span> <strong>{{ $pilier->intitule }}</strong></div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark montant">{{ $fmt($totPilier) }} XAF</span>
            @include('rbm._actions', ['niveau'=>'pilier','noeud'=>$pilier,'enfant'=>'axe'])
        </div>
    </div>
    <div class="rbm-niveau lvl-pilier">
        @foreach ($pilier->axes as $axe)
            @if(RbmStatutFiltre::visible($axe, 'axe', $statutFiltre))
            <div class="rbm-item lvl-axe d-flex justify-content-between align-items-center @if(RbmStatutFiltre::statut($axe, 'axe')==='archive') rbm-archive @endif">
                <div><span class="badge bg-success">AXE</span> <span class="rbm-code">{{ $axe->code }}</span> {{ $axe->intitule }}</div>
                @include('rbm._actions', ['niveau'=>'axe','noeud'=>$axe,'enfant'=>'produit'])
            </div>
            <div class="rbm-niveau lvl-axe">
                @foreach ($axe->produits as $produit)
                    @if(RbmStatutFiltre::visible($produit, 'produit', $statutFiltre))
                    <div class="rbm-item lvl-produit d-flex justify-content-between align-items-center @if(RbmStatutFiltre::statut($produit, 'produit')==='archive') rbm-archive @endif">
                        <div><span class="badge bg-warning text-dark">PRODUIT</span> <span class="rbm-code">{{ $produit->code }}</span> {{ $produit->intitule }}</div>
                        @include('rbm._actions', ['niveau'=>'produit','noeud'=>$produit,'enfant'=>'sous_produit'])
                    </div>
                    <div class="rbm-niveau lvl-produit">
                        @foreach ($produit->sousProduits as $sp)
                            @if(RbmStatutFiltre::visible($sp, 'sous_produit', $statutFiltre))
                            <div class="rbm-item lvl-sous_produit d-flex justify-content-between align-items-center @if(RbmStatutFiltre::statut($sp, 'sous_produit')==='archive') rbm-archive @endif">
                                <div><span class="badge" style="background:#6f42c1">SOUS-PRODUIT</span> <span class="rbm-code">{{ $sp->code }}</span> {{ $sp->intitule }}</div>
                                @include('rbm._actions', ['niveau'=>'sous_produit','noeud'=>$sp,'enfant'=>'activite'])
                            </div>
                            <div class="rbm-niveau lvl-sous_produit">
                                @foreach ($sp->activites as $act)
                                    @if(RbmStatutFiltre::visible($act, 'activite', $statutFiltre))
                                    <div class="rbm-item lvl-activite d-flex justify-content-between align-items-center @if(RbmStatutFiltre::statut($act, 'activite')==='archive') rbm-archive @endif">
                                        <div><span class="badge bg-info text-dark">ACTIVITÉ</span> <span class="rbm-code">{{ $act->code }}</span> {{ \Illuminate\Support\Str::limit($act->intitule, 80) }}
                                            @if($act->trim_1)<span class="badge bg-light text-dark">1T</span>@endif
                                            @if($act->trim_2)<span class="badge bg-light text-dark">2T</span>@endif
                                            @if($act->trim_3)<span class="badge bg-light text-dark">3T</span>@endif
                                            @if($act->trim_4)<span class="badge bg-light text-dark">4T</span>@endif
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-light text-dark montant" title="CEEAC {{ $fmt($act->montant_ceeac) }} · PTF {{ $fmt($act->montant_ptf) }}">{{ $fmt($act->montant_total) }}</span>
                                            @include('rbm._actions', ['niveau'=>'activite','noeud'=>$act,'enfant'=>'tache'])
                                        </div>
                                    </div>
                                    <div class="rbm-niveau lvl-activite">
                                        @foreach ($act->taches as $tache)
                                            @if(RbmStatutFiltre::visible($tache, 'tache', $statutFiltre))
                                            <div class="rbm-item lvl-tache d-flex justify-content-between align-items-center @if(RbmStatutFiltre::statut($tache, 'tache')==='archive') rbm-archive @endif">
                                                <div><span class="badge bg-secondary">TÂCHE</span> <span class="rbm-code">{{ $tache->code }}</span> {{ \Illuminate\Support\Str::limit($tache->intitule, 80) }} <span class="badge bg-light text-dark">{{ str_replace('_',' ',$tache->statut) }}</span></div>
                                                @include('rbm._actions', ['niveau'=>'tache','noeud'=>$tache,'enfant'=>null])
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                            @endif
                        @endforeach
                    </div>
                    @endif
                @endforeach
            </div>
            @endif
        @endforeach
    </div>
    @endif
@empty
    <p class="text-muted mb-0">Aucun pilier défini pour l'exercice {{ $exercice->annee }}. Commencez par ajouter un pilier.</p>
@endforelse
</div></div>

{{-- Formulaire unique des actions de nœud (archiver, supprimer, restaurer). --}}
<form id="rbm-action-form" method="POST" class="d-none">
    @csrf
    <input type="hidden" name="_method" value="POST">
    <input type="hidden" name="exercice" value="{{ $ex }}">
</form>
@endsection

@push('scripts')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.addEventListener('click', function (e) {
    var bouton = e.target.closest('[data-rbm-action]');
    if (!bouton) { return; }
    if (bouton.dataset.rbmConfirmer && !confirm(bouton.dataset.rbmConfirmer)) { return; }
    var form = document.getElementById('rbm-action-form');
    form.action = bouton.dataset.rbmAction;
    form.querySelector('[name=_method]').value = bouton.dataset.rbmMethode || 'POST';
    bouton.disabled = true;
    form.submit();
});
</script>
@endpush
