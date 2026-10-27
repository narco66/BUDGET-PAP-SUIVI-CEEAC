<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre', 'Accès') · {{ config('app.name') }}</title>
    <link href="{{ asset('vendor/bootstrap-5.3.3/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons-1.11.3/bootstrap-icons.min.css') }}" rel="stylesheet">
    <style>
        :root { --ceeac: #0a4a8f; --ceeac-dark: #073564; }
        body {
            background: linear-gradient(135deg, var(--ceeac-dark), var(--ceeac));
            min-height: 100vh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .auth-card { width: 100%; max-width: 440px; }
        .brand-title { color: var(--ceeac); font-weight: 700; letter-spacing: .02em; }
        a { color: var(--ceeac); }
        .btn-ceeac { background: var(--ceeac); border-color: var(--ceeac); color: #fff; }
        .btn-ceeac:hover, .btn-ceeac:focus { background: var(--ceeac-dark); border-color: var(--ceeac-dark); color: #fff; }
        .form-control:focus { border-color: var(--ceeac); box-shadow: 0 0 0 .2rem rgba(10,74,143,.18); }
    </style>
    @stack('styles')
</head>
<body class="d-flex flex-column align-items-center justify-content-center p-3">
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-bank2 fs-1" style="color: var(--ceeac)"></i>
                <h4 class="brand-title mt-2 mb-0">GEST-BUDGET-PAP</h4>
                <small class="text-muted">Commission de la CEEAC</small>
                <div class="text-muted" style="font-size:.72rem">@yield('sous-titre', 'Communauté Économique des États de l’Afrique Centrale')</div>
            </div>

            @yield('contenu')
        </div>
    </div>
    <p class="text-center text-white-50 small mt-3 mb-0">© {{ now()->year }} Commission de la CEEAC · Système GEST-BUDGET-PAP</p>
</body>
</html>
