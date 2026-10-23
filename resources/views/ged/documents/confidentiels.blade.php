@extends('layouts.app')
@section('titre', 'Documents confidentiels')
@section('contenu')
<h4 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2"></i>Documents confidentiels</h4>
@include('ged.partials._liste-documents', ['documents' => $documents])
@endsection
