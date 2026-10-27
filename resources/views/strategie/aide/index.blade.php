@extends('layouts.app')
@section('titre', 'Manuel utilisateur — Planification stratégique')

@section('contenu')
@include('strategie._nav', ['active' => 'aide'])

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">Manuel utilisateur</h1>
        <p class="text-muted small mb-0">Guide du module Planification stratégique institutionnelle</p>
    </div>
    <div class="d-flex flex-wrap gap-1">
        <a href="{{ route('strategie.aide.pdf') }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>Télécharger PDF</a>
        <a href="{{ route('strategie.aide.word') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-word me-1"></i>Télécharger Word</a>
        <a href="{{ route('strategie.hub') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour au hub</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-3 d-none d-lg-block">
        <nav class="card border-0 shadow-sm sticky-top strategie-sommaire" aria-label="Sommaire du manuel">
            <div class="card-body py-2">
                <div class="small fw-semibold text-muted text-uppercase mb-2">Sommaire</div>
                <ol class="list-unstyled small mb-0" id="sommaireManuel"></ol>
            </div>
        </nav>
    </div>
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-body strategie-manuel prose" id="corpsManuel">
                {{ \App\Support\SafeHtml::render($corps) }}
            </div>
        </div>
    </div>
</div>

<style>
.strategie-manuel.prose h1 { font-size: 1.35rem; color: #0a4a8f; margin-bottom: 1rem; }
.strategie-manuel.prose h2 { font-size: 1.1rem; color: #0a4a8f; border-bottom: 1px solid #dee2e6; padding-bottom: .35rem; margin-top: 1.5rem; scroll-margin-top: 1rem; }
.strategie-manuel.prose h3 { font-size: 1rem; margin-top: 1rem; }
.strategie-manuel.prose table { width: 100%; font-size: .875rem; }
.strategie-manuel.prose th { background: #0a4a8f; color: #fff; }
.strategie-manuel.prose th, .strategie-manuel.prose td { border: 1px solid #dee2e6; padding: .35rem .5rem; }
.strategie-manuel.prose code { background: #f8f9fa; padding: 0 .2rem; border-radius: 2px; }
.strategie-manuel.prose pre { background: #f8f9fa; padding: .75rem; border-radius: .25rem; font-size: .8rem; }
.strategie-manuel.prose blockquote { border-left: 3px solid #0a4a8f; padding-left: .75rem; color: #495057; }
.strategie-sommaire { top: 1rem; max-height: calc(100vh - 2rem); overflow-y: auto; }
.strategie-sommaire a { display: block; padding: .15rem 0; color: var(--bs-body-color); text-decoration: none; }
.strategie-sommaire a:hover, .strategie-sommaire a.active { color: #0a4a8f; font-weight: 600; }
</style>
@endsection

@push('scripts')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
// Sommaire généré depuis les titres de section du manuel (h2), section courante surlignée au défilement.
document.addEventListener('DOMContentLoaded', function () {
    const liste = document.getElementById('sommaireManuel');
    const titres = Array.from(document.querySelectorAll('#corpsManuel h2'));
    if (!liste || !titres.length) return;
    const liens = titres.map(function (h2, i) {
        h2.id = h2.id || 'section-' + (i + 1);
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = '#' + h2.id;
        a.textContent = h2.textContent;
        li.appendChild(a);
        liste.appendChild(li);
        return a;
    });
    if (!('IntersectionObserver' in window)) return;
    const observateur = new IntersectionObserver(function (entrees) {
        entrees.forEach(function (e) {
            if (!e.isIntersecting) return;
            liens.forEach(function (a) { a.classList.toggle('active', a.getAttribute('href') === '#' + e.target.id); });
        });
    }, { rootMargin: '0px 0px -75% 0px' });
    titres.forEach(function (h2) { observateur.observe(h2); });
});
</script>
@endpush
