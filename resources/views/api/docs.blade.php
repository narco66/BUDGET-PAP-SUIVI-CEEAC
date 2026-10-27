@extends('layouts.app')
@section('titre', 'Documentation API')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-book me-2"></i>Documentation API REST v1</h4>
    <a href="{{ route('admin.interoperabilite.index') }}" class="btn btn-sm btn-outline-secondary">Portail interop</a>
</div>
<div id="swagger-ui" class="bg-white border rounded shadow-sm p-2"></div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.addEventListener('DOMContentLoaded', () => {
    SwaggerUIBundle({
        url: @json($specUrl),
        dom_id: '#swagger-ui',
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
        layout: 'BaseLayout',
        requestInterceptor: (req) => {
            req.headers['Accept'] = 'application/json';
            return req;
        },
    });
});
</script>
@endpush
