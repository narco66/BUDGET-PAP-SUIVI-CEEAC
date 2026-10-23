@extends('layouts.app')
@section('titre', 'Documents expirés')
@section('contenu')
<h4 class="fw-bold mb-3"><i class="bi bi-hourglass-split me-2"></i>Documents expirés</h4>
@include('ged.partials._liste-documents', ['documents' => $documents])
@endsection
