<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 110px 40px 70px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color:#222; }
        header { position: fixed; top: -85px; left: 0; right: 0; height: 80px; border-bottom:2px solid #0a4a8f; }
        footer { position: fixed; bottom: -50px; left:0; right:0; height:40px; border-top:1px solid #ccc; font-size:9px; color:#666; text-align:center; padding-top:6px; }
        .org { font-size:10px; color:#0a4a8f; font-weight:bold; }
        .titre-doc { text-align:center; font-size:16px; font-weight:bold; color:#0a4a8f; margin:6px 0 2px; }
        .numero { text-align:center; font-size:11px; color:#555; margin-bottom:14px; }
        table.infos { width:100%; border-collapse:collapse; margin-bottom:12px; }
        table.infos td { padding:4px 6px; border:1px solid #d5dce6; vertical-align:top; }
        table.infos td.k { background:#eef3fa; font-weight:bold; width:30%; }
        table.lignes { width:100%; border-collapse:collapse; margin-top:6px; }
        table.lignes th { background:#0a4a8f; color:#fff; padding:5px; font-size:10px; }
        table.lignes td { border:1px solid #d5dce6; padding:5px; }
        .montant { text-align:right; font-variant-numeric: tabular-nums; }
        .total-row td { font-weight:bold; background:#eef3fa; }
        .signatures { width:100%; margin-top:36px; }
        .signatures td { width:33%; text-align:center; font-size:10px; padding-top:30px; border-top:1px solid #999; }
        .badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:10px; }
    </style>
</head>
<body>
    <header>
        <div class="org">COMMUNAUTÉ ÉCONOMIQUE DES ÉTATS DE L'AFRIQUE CENTRALE</div>
        <div style="font-size:10px;">Commission de la CEEAC &middot; GEST-BUDGET-PAP</div>
        <div class="titre-doc">@yield('titre_doc')</div>
        <div class="numero">N° @yield('numero') &middot; Exercice @yield('exercice')</div>
    </header>
    <footer>
        Document généré automatiquement le {{ $genere_le ?? now()->format('d/m/Y H:i') }} &middot; Commission de la CEEAC
    </footer>
    <main>
        @yield('corps')
    </main>
</body>
</html>
