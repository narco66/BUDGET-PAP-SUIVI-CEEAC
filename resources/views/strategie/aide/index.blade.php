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

<div class="card border-0 shadow-sm">
    <div class="card-body strategie-manuel prose">
        {{ \App\Support\SafeHtml::render($corps) }}
    </div>
</div>

<style>
.strategie-manuel.prose h1 { font-size: 1.35rem; color: #0a4a8f; margin-bottom: 1rem; }
.strategie-manuel.prose h2 { font-size: 1.1rem; color: #0a4a8f; border-bottom: 1px solid #dee2e6; padding-bottom: .35rem; margin-top: 1.5rem; }
.strategie-manuel.prose h3 { font-size: 1rem; margin-top: 1rem; }
.strategie-manuel.prose table { width: 100%; font-size: .875rem; }
.strategie-manuel.prose th { background: #0a4a8f; color: #fff; }
.strategie-manuel.prose th, .strategie-manuel.prose td { border: 1px solid #dee2e6; padding: .35rem .5rem; }
.strategie-manuel.prose code { background: #f8f9fa; padding: 0 .2rem; border-radius: 2px; }
.strategie-manuel.prose pre { background: #f8f9fa; padding: .75rem; border-radius: .25rem; font-size: .8rem; }
.strategie-manuel.prose blockquote { border-left: 3px solid #0a4a8f; padding-left: .75rem; color: #495057; }
</style>
@endsection
