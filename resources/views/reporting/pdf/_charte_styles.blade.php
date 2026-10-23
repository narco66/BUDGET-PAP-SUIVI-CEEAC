<style>
    * { font-family: {{ $charte['police_pdf'] ?? 'DejaVu Sans, sans-serif' }}; }
    body { font-size: 10px; color: #222; margin: 0; }
    .entete { border-bottom: 3px solid {{ $charte['couleur_primaire'] }}; padding-bottom: 6px; margin-bottom: 10px; }
    .entete .inst { color: {{ $charte['couleur_primaire'] }}; font-weight: bold; font-size: 12px; letter-spacing: .04em; }
    .entete .sub { color: {{ $charte['couleur_texte_secondaire'] }}; font-size: 8px; font-style: italic; }
    .entete .sys { color: {{ $charte['couleur_fonce'] }}; font-size: 7px; margin-top: 2px; }
    h1.titre { color: {{ $charte['couleur_primaire'] }}; font-size: 17px; margin: 12px 0 2px; }
    .soustitre { color: #555; font-size: 10px; margin-bottom: 2px; }
    .ref { color: #999; font-size: 8px; margin-bottom: 10px; }
    h2.section { color: {{ $charte['couleur_primaire'] }}; font-size: 12px; margin: 14px 0 5px; border-left: 3px solid {{ $charte['couleur_primaire'] }}; padding-left: 6px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    th { background: {{ $charte['couleur_primaire'] }}; color: #fff; font-size: 8px; padding: 4px 5px; text-align: left; }
    td { font-size: 8px; padding: 3px 5px; border-bottom: 1px solid #e3e3e3; }
    td.r, th.r { text-align: right; }
    tr.total td { font-weight: bold; background: {{ $charte['couleur_fond_tableau'] }}; border-top: 2px solid {{ $charte['couleur_primaire'] }}; }
    .kpis { width: 100%; }
    .kpi { display: inline-block; width: 23%; border: 1px solid #d9e2ec; border-radius: 4px; padding: 6px 8px; margin: 0 1% 6px 0; }
    .kpi .lbl { color: #777; font-size: 8px; }
    .kpi .val { color: {{ $charte['couleur_primaire'] }}; font-size: 14px; font-weight: bold; }
    ul { margin: 2px 0 6px 14px; padding: 0; }
    li { font-size: 9px; margin-bottom: 2px; }
    p.txt { font-size: 10px; text-align: justify; margin: 2px 0 6px; }
    .niv0 td:first-child { font-weight: bold; color: {{ $charte['couleur_fonce'] }}; background: {{ $charte['couleur_fond_tableau'] }}; }
    .niv1 td:first-child { font-weight: bold; padding-left: 12px; }
    .niv2 td:first-child { padding-left: 22px; color: #444; }
    .niv3 td:first-child { padding-left: 32px; color: #666; }
    .niv4 td:first-child { padding-left: 42px; }
    .pied { margin-top: 18px; border-top: 1px solid #ccc; padding-top: 6px; }
    .pied .mention { font-size: 7px; color: #888; margin-top: 4px; }
    .pied .lien { color: {{ $charte['couleur_primaire'] }}; }
</style>
