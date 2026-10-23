@extends('layouts.app')
@section('titre', 'Archives électroniques')

@section('contenu')
@include('ged._nav-module', ['page' => 'archives'])
<h4 class="fw-bold mb-3">Archives électroniques</h4>
@include('ged.partials._liste-documents', ['documents' => $documents])
@endsection
