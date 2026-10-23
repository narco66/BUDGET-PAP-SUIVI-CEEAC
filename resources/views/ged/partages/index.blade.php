@extends('layouts.app')
@section('titre', 'Documents partagés')
@section('contenu')
<h4 class="fw-bold mb-3">Documents partagés avec moi</h4>
@include('ged.partials._liste-documents', ['documents' => $documents])
@endsection
