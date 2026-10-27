# 15 — Rapport final de conformité (vivant)

**Date d’ouverture :** 24/10/2026.  
Ce fichier est actualisé à chaque module livré. Il ne déclare pas une conformité globale tant que ENG–PAY et les tests critiques ne sont pas verts.

## Verdict par module (cible procédures 2026)

| Module | Verdict | Commentaire |
|---|---|---|
| EB | Conforme avec réserve | Circuits 2026, sous-lignes, ENG unique, `a_corriger` + PUT, catégorie figée. Mapping DRHMG / Figma / Playwright. |
| ENG | Conforme avec réserve | Budget+CF + LIQ auto. EB obligatoire. Dégagement plafonné. Mapping DIR-BUDGET à valider. |
| LIQ | Conforme avec réserve | Service fait, tranches, doublon facture. Brut/retenues/net saisis (identité arithmétique, pas de barème). |
| ORD | Conforme avec réserve | Seuil versionné, routage SG/Président, délégation écrite SG, alerte anti-fractionnement (non bloquante). Flag juridique faux. |
| PAY | Conforme avec réserve | Reliquat, motif, RIB/mode, Agent comptable, changement RIB = visa Agent ≠ saisie, rapprochement ≠ visa, contrepassation ; clôture rouverte après rejet d’instrument. |
| Chaîne | Conforme avec réserve | Transitions auto + clôture persistée ; rouverture si non soldée ; lignes inactives refusées à l’aval. Cockpit, exports, vérification QR SPA, dossier unifié, consolidation D1. |
| Marchés / Contrats | Porté SPA | CRUD session `/api/v1/marches` et `/contrats` ; montants décimaux ; droits `marche.*` / `contrat.*` inchangés. Pas de règle PPM inventée. |
| Suivi / reco / formations / immos | Porté SPA | Hub S&E, campagnes, reco, formations, immos. Circuit collecte + justificatifs GED (`POST /api/v1/collectes/{id}/justificatifs`, droit `ged.creer`). Pas de binaire/hash dans le JSON. |
| Reporting personnalisé / suivi financier | Porté SPA | Modèles `reporting/modeles` + hub `/suivi-financier` (synthèse API existante enrichie, anomalies, capture). |
| Reporting cockpit / planification | Porté SPA | `/reporting/cockpit` et `/reporting/planification` ; mêmes services Blade, montants décimaux, exports retard en téléchargement Blade. |
| GED recherche / wiki | Porté SPA | `/ged/recherche` (GedSearchService + sauvegardes) et `/ged/wiki` (CRUD connaissances, Markdown serveur). Pas de binaire en JSON. |
| GED classement / vues / admin | Porté SPA | Classement, catégories, types, dossiers, versions, circuits, partages, confidentiels, expirés, archives, conservation, journal, paramètres. Droits `ged.admin` / `ged.consulter_journal` inchangés. |
| Audit workflow | Porté SPA | `GET /api/v1/workflow/audit` + `/workflow/audit`. Droit `workflow.audit` (alias `audit.voir` / rôle auditeur). Pas d’IP / user-agent / meta. Export CSV reste Blade. |
| Modèles e-mail | Porté SPA | `GET/PUT /api/v1/notifications/templates` + `/notifications/templates`. Droit `notifications.admin`. Catalogue seeder, pas de création/suppression. Corps HTML non exécuté dans le SPA. |
| Hub interopérabilité | Porté SPA | `GET /api/v1/interoperabilite/hub` + `/interoperabilite`. KPI + liens SPA (apps, journal, webhooks, monitoring, docs, exports). |
| Applications connectées | Porté SPA | CRUD `/interoperabilite/apps`. Droit `api.admin`. Secret client uniquement à la création, jamais relu. |
| Journal API | Porté SPA | `GET /api/v1/interoperabilite/journal` + `/interoperabilite/journal`. Droit `api.monitor` / `api.admin`. User-agent, hash et nom de jeton exclus. |
| Webhooks | Porté SPA | CRUD `/interoperabilite/webhooks`. Droit `api.admin`. Secret HMAC et payload de livraison exclus du JSON. |
| Monitoring API | Porté SPA | `GET /api/v1/interoperabilite/monitoring` + `/interoperabilite/monitoring`. Droit `api.monitor` / `api.admin`. Agrégats Blade, sans payload. |
| Documentation API | Porté SPA | `GET /api/v1/interoperabilite/docs` + `/interoperabilite/docs`. Droit `api.view` / `api.admin`. OpenAPI 3 + Swagger UI, spec fichier existant. |
| Exports interop | Porté SPA | Inventaire csv/xlsx (`api.admin` / `api.publish`) et rapport sécurité csv/pdf (`api.admin` / `api.monitor`). Mêmes fichiers que Blade. |
| UI Figma | Bloqué | Maquettes absentes |
| Playwright | Absent | PHPUnit en relais |
| Données protégées | Conforme à ce stade | — |

## Ne pas affirmer

PHPUnit 24/10/2026 : **74 passed** (filtre chaîne + cockpit + dossier/consolidation/RIB). Marchés/Contrats SPA : `MarchesContratsTest`. Figma et Playwright restent hors périmètre.
