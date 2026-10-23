<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre', 'Vérification') · Commission de la CEEAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --ceeac: #0a4a8f; --ceeac-dark: #073564; }
        body {
            background: #f4f6f9;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .brand { color: var(--ceeac); }
        a { color: var(--ceeac); }
        .public-band { height: 4px; background: linear-gradient(90deg, var(--ceeac-dark), var(--ceeac)); }
    </style>
    @stack('styles')
</head>
<body>
    <div class="public-band"></div>
    <div class="container py-5" style="max-width: 680px">
        <div class="text-center mb-4">
            <i class="bi bi-bank2 fs-1 brand"></i>
            <h4 class="brand fw-bold mt-2 mb-0">Commission de la CEEAC</h4>
            <div class="text-muted">@yield('sous-titre', 'Système GEST-BUDGET-PAP')</div>
        </div>

        @yield('contenu')

        <p class="text-center text-muted small mt-3">© {{ now()->year }} Commission de la CEEAC · Système GEST-BUDGET-PAP</p>
    </div>
</body>
</html>
