# 04 — Audit et corrections EB

## Conforme à l’origine

- CRUD brouillon, numérotation, permissions, périmètre, workflow générique, pièces, PDF interne, API SPA, montants décimaux.

## Incomplet

- Figma. PDF EB avec QR interne (page `depense.verifier`).

## Absent

- Catégorie PAP / hors PAP, sous-lignes, circuits Commissaire/SG et DRHMG/SG/Président, génération ENG unique, Figma, Playwright.

## Corrections (Phase B) — réalisées

- `categorie` PAP / hors PAP, table `expression_besoin_lignes`, totaux serveur.
- Instantané `heritage_pap` et alerte crédits non bloquante.
- Circuits `BESOIN_PAP`, `BESOIN_PAP_APPUI` (structure `SG` / types d’appui), `BESOIN_HORS_PAP` (l’ancien `BESOIN` est conservé pour l’historique).
- Soumission / création refusées si l’exercice est clos. Convertir ENG idempotent (clé + unique).
- Génération ENG unique (`BesoinVersEngagementService`, contrainte unique si pas de doublons).
- Retour workflow `a_corriger` ; API PUT de reprise ; catégorie PAP/hors PAP figée après soumission ; suppression d’EB bloquée tant qu’un ENG lié existe.
- API / Vue : création guidée, affichage héritage et sous-lignes ; filtres `a_corriger` / catégorie ; PDF session ; pièces du dossier ; justification hors PAP ; message si aucune activité PAP dans le périmètre.
- Tests : `ChaineDepenseEbConformiteTest` (3) + `DepenseSpaApiTest` mis à jour — **8/8 verts**.

## Écarts résiduels / décisions

- Mapping **DRHMG** : aucun rôle Spatie homonyme. Paramètre `chaine_depense.role_drhmg` (défaut `directeur`) — **à valider institutionnellement**.
- Directions d’appui : paramètre `types_structure_appui` / codes. Sans Figma, UI Vue/Bootstrap.
