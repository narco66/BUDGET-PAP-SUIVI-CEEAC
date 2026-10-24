# 12 — GED et PDF

## Conforme

- `HasPieces` / GED SHA-256 module documents, templates DomPDF besoin / engagement / étape.
- API session `GET /api/v1/besoins/{id}/pdf`, `GET /api/v1/engagements/{id}/pdf` (+ certificat), `GET /api/v1/depense/{type}/{id}/pdf` ; boutons SPA.
- Pièces du dossier (`GET/POST /api/v1/pieces/{type}/{id}`) : dépôt lié à `update`/`agir` sur l’objet, **sans** exiger `ged.creer` ni un fournisseur.
- Vérification d’empreinte session : `GET /api/v1/verification/{type}/{id}` (complément de `depense.verifier`) et écran SPA `/verification/{type}/{id}`.

## Écarts

- Maquettes Figma et modèles officiels **absents**.
- QR interne : URL SPA `/verification/{type}/{id}` (`SPA_URL`) ; Blade `depense.verifier` reste disponible. Une session est exigée ; portail public non inventé.
- Pas de comparaison visuelle automatisée.

## Règle

Ne pas inventer un « modèle officiel ». Enrichir les templates internes (logo existant, références, montants) et documenter l’écart Figma.
