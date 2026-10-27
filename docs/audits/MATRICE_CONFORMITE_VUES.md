# Matrice de conformité par vue — BUDGET-CEEAC / GESBUDEP

**Date :** 26 octobre 2026. Une vue n'est classée **conforme** que si les contrôles exécutés le prouvent : chargement HTTP 200 sans erreur JavaScript, aucune violation WCAG mesurée, aucun débordement sur les 4 formats (1920, 1366, 768, 390 px), et, pour les données, concordance vérifiée avec PostgreSQL. **Partiel** : une partie des contrôles échoue ou n'a pas pu être exécutée. **Non vérifiée** : vue non atteinte (paramètre sans exemple, autre profil non testable, fichier).

| Module | Vue (route) | Design / accessibilité | Ergonomie (responsive) | Fonctionnalité (chargement) | Données | Performance (chargement) | Statut |
|---|---|---|---|---|---|---|---|
| admin | `/admin/interoperabilite` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 170 ms | Conforme (hors données) |
| admin | `/admin/interoperabilite/apps` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 698 ms | Conforme (hors données) |
| admin | `/admin/interoperabilite/apps/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 734 ms | Conforme (hors données) |
| admin | `/admin/interoperabilite/apps/{app}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 734 ms | Conforme (hors données) |
| admin | `/admin/interoperabilite/apps/{app}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| admin | `/admin/interoperabilite/exports/securite/{format}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| admin | `/admin/interoperabilite/journal` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 516 ms | Conforme (hors données) |
| admin | `/admin/interoperabilite/monitoring` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 549 ms | Conforme (hors données) |
| admin | `/admin/interoperabilite/webhooks` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 569 ms | Conforme (hors données) |
| admin | `/admin/interoperabilite/webhooks/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 491 ms | Conforme (hors données) |
| admin | `/admin/interoperabilite/webhooks/{webhook}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 491 ms | Conforme (hors données) |
| admin | `/admin/interoperabilite/webhooks/{webhook}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| admin | `/admin/notifications/templates` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 517 ms | Conforme (hors données) |
| admin | `/admin/notifications/templates/{template}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| admin | `/admin/workflows` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 643 ms | Conforme (hors données) |
| admin | `/admin/workflows/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 616 ms | Conforme (hors données) |
| admin | `/admin/workflows/{workflow}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| administration-technique | `/administration-technique` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 936 ms | Conforme (hors données) |
| administration-technique | `/administration-technique/files-attente` | 🟡 3 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 562 ms | Partiel |
| administration-technique | `/administration-technique/files-attente/echoues/{uuid}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| alertes | `/alertes` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 678 ms | Conforme (hors données) |
| alertes | `/alertes/export/csv` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| appels-fonds | `/appels-fonds` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 550 ms | Conforme (hors données) |
| appels-fonds | `/appels-fonds/create` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 150 ms | Partiel |
| appels-fonds | `/appels-fonds/{appelsFond}` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 150 ms | Partiel |
| audit | `/audit` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 644 ms | Partiel |
| audit | `/audit/dossiers/{type}/{id}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| audit | `/audit/integrite` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 777 ms | Conforme (hors données) |
| audit | `/audit/securite` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 676 ms | Conforme (hors données) |
| audit | `/audit/tableau-de-bord` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 376 ms | Partiel |
| audit | `/audit/utilisateurs/{user}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| audit | `/audit/{journal}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 777 ms | Conforme (hors données) |
| audit-missions | `/audit-missions` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 660 ms | Conforme (hors données) |
| audit-missions | `/audit-missions/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 609 ms | Conforme (hors données) |
| audit-missions | `/audit-missions/{mission}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 609 ms | Conforme (hors données) |
| audit-missions | `/audit-missions/{mission}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| besoins | `/besoins` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | 🟡 4 042 ms | Conforme |
| besoins | `/besoins/consolidation` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 725 ms | Conforme (hors données) |
| besoins | `/besoins/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 066 ms | Conforme (hors données) |
| besoins | `/besoins/{besoin}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 725 ms | Conforme (hors données) |
| besoins | `/besoins/{besoin}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| budget | `/budget` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | 🟡 3 446 ms | Partiel |
| budget | `/budget/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 522 ms | Conforme (hors données) |
| budget | `/budget/enveloppe` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 171 ms | Conforme (hors données) |
| budget | `/budget/enveloppe/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 984 ms | Conforme (hors données) |
| budget | `/budget/enveloppe/{budget}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 984 ms | Conforme (hors données) |
| budget | `/budget/enveloppe/{budget}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| budget | `/budget/enveloppe/{budget}/preparations` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| budget | `/budget/enveloppe/{budget}/rapport` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| budget | `/budget/enveloppe/{budget}/revisions` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| budget | `/budget/enveloppe/{budget}/simulations` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| budget | `/budget/import` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 614 ms | Conforme (hors données) |
| budget | `/budget/rapport` | 🟡 3 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 5 265 ms | Partiel |
| budget | `/budget/{ligne}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| conformite | `/conformite` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 687 ms | Conforme (hors données) |
| conformite | `/conformite/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 728 ms | Conforme (hors données) |
| conformite | `/conformite/{nonConformite}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 728 ms | Conforme (hors données) |
| conformite | `/conformite/{nonConformite}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| contrats | `/contrats` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 767 ms | Conforme (hors données) |
| contrats | `/contrats/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 239 ms | Conforme (hors données) |
| contrats | `/contrats/{contrat}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 239 ms | Conforme (hors données) |
| contrats | `/contrats/{contrat}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| controle-interne | `/controle-interne` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 4 330 ms | Conforme (hors données) |
| controle-interne | `/controle-interne/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 854 ms | Conforme (hors données) |
| controle-interne | `/controle-interne/{controle}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| conventions | `/conventions/{convention}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| conventions | `/conventions/{convention}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| dashboard | `/dashboard` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | 🟡 3 011 ms | Conforme |
| depense | `/depense/dossier/besoin/{besoin}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| depense | `/depense/dossier/engagement/{engagement}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| depense | `/depense/rapprochement` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 264 ms | Conforme (hors données) |
| depense | `/depense/rapprochement/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 835 ms | Conforme (hors données) |
| depense | `/depense/rapprochement/{rapprochement}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 835 ms | Conforme (hors données) |
| depense | `/depense/verifier/{type}/{id}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| depense | `/depense/{type}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | 🟡 4 332 ms | Conforme |
| depense | `/depense/{type}/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 835 ms | Conforme (hors données) |
| depense | `/depense/{type}/{id}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 835 ms | Conforme (hors données) |
| depense-cockpit | `/depense-cockpit` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | 🟡 3 569 ms | Conforme |
| encaissements | `/encaissements` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 694 ms | Conforme (hors données) |
| encaissements | `/encaissements/create` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 458 ms | Partiel |
| encaissements | `/encaissements/{encaissement}` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 458 ms | Partiel |
| engagements | `/engagements` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | 🟡 2 274 ms | Conforme |
| engagements | `/engagements/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 771 ms | Conforme (hors données) |
| engagements | `/engagements/{engagement}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 771 ms | Conforme (hors données) |
| exercices | `/exercices` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 150 ms | Conforme (hors données) |
| exercices | `/exercices/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 635 ms | Conforme (hors données) |
| exercices | `/exercices/{exercice}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 635 ms | Conforme (hors données) |
| exercices | `/exercices/{exercice}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| fonctions | `/fonctions` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 800 ms | Conforme (hors données) |
| fonctions | `/fonctions/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 697 ms | Conforme (hors données) |
| fonctions | `/fonctions/{fonction}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| formations | `/formations` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 682 ms | Conforme (hors données) |
| formations | `/formations/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 596 ms | Conforme (hors données) |
| formations | `/formations/{formation}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 596 ms | Conforme (hors données) |
| formations | `/formations/{formation}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| ged | `/ged` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 065 ms | Conforme (hors données) |
| ged | `/ged/archives` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 327 ms | Conforme (hors données) |
| ged | `/ged/categories` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 612 ms | Conforme (hors données) |
| ged | `/ged/classement` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 079 ms | Partiel |
| ged | `/ged/connaissances` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 051 ms | Conforme (hors données) |
| ged | `/ged/connaissances/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 601 ms | Conforme (hors données) |
| ged | `/ged/connaissances/{connaissance}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 601 ms | Conforme (hors données) |
| ged | `/ged/connaissances/{connaissance}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| ged | `/ged/conservation` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 599 ms | Conforme (hors données) |
| ged | `/ged/documents` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 078 ms | Conforme (hors données) |
| ged | `/ged/documents/confidentiels` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 903 ms | Conforme (hors données) |
| ged | `/ged/documents/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 919 ms | Conforme (hors données) |
| ged | `/ged/documents/expires` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 822 ms | Conforme (hors données) |
| ged | `/ged/documents/{document}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 903 ms | Conforme (hors données) |
| ged | `/ged/documents/{document}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| ged | `/ged/dossiers` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 672 ms | Conforme (hors données) |
| ged | `/ged/exports/documents/{document}/bordereau-archivage` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| ged | `/ged/exports/documents/{document}/bordereau-depot` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| ged | `/ged/exports/documents/{document}/certificat-integrite` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| ged | `/ged/exports/documents/{document}/certificat-signature` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| ged | `/ged/exports/documents/{document}/fiche` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| ged | `/ged/journal` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 823 ms | Conforme (hors données) |
| ged | `/ged/parametres` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 751 ms | Conforme (hors données) |
| ged | `/ged/partages` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 445 ms | Conforme (hors données) |
| ged | `/ged/recherche` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 365 ms | Conforme (hors données) |
| ged | `/ged/signatures` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 555 ms | Conforme (hors données) |
| ged | `/ged/types` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 626 ms | Conforme (hors données) |
| ged | `/ged/validations` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 568 ms | Conforme (hors données) |
| ged | `/ged/versions` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 442 ms | Conforme (hors données) |
| ged | `/ged/workflows` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 658 ms | Conforme (hors données) |
| gouvernance | `/gouvernance/cockpit` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 5 947 ms | Conforme (hors données) |
| immobilisations | `/immobilisations` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 988 ms | Conforme (hors données) |
| immobilisations | `/immobilisations/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 586 ms | Conforme (hors données) |
| immobilisations | `/immobilisations/{immobilisation}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 586 ms | Conforme (hors données) |
| immobilisations | `/immobilisations/{immobilisation}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| incidents | `/incidents` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 796 ms | Conforme (hors données) |
| incidents | `/incidents/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 018 ms | Conforme (hors données) |
| incidents | `/incidents/{incident}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 018 ms | Conforme (hors données) |
| incidents | `/incidents/{incident}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| institution | `/institution` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 236 ms | Conforme (hors données) |
| login | `/login` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| logs | `/logs/connexions` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 568 ms | Conforme (hors données) |
| marches | `/marches` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 115 ms | Conforme (hors données) |
| marches | `/marches/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 240 ms | Conforme (hors données) |
| marches | `/marches/{marche}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 240 ms | Conforme (hors données) |
| marches | `/marches/{marche}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| mes-taches | `/mes-taches` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 181 ms | Conforme (hors données) |
| mes-taches | `/mes-taches/supervision` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 664 ms | Partiel |
| mes-taches | `/mes-taches/{tache}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 553 ms | Conforme (hors données) |
| mfa | `/mfa` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| mfa | `/mfa/challenge` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| mon-mot-de-passe | `/mon-mot-de-passe` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 012 ms | Conforme (hors données) |
| nomenclature | `/nomenclature` | 🟡 24 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 268 ms | Partiel |
| nomenclature | `/nomenclature/assistant` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 015 ms | Conforme (hors données) |
| nomenclature | `/nomenclature/tableau-bord` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 873 ms | Conforme (hors données) |
| nomenclature | `/nomenclature/{niveau}/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 888 ms | Conforme (hors données) |
| nomenclature | `/nomenclature/{niveau}/{id}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 888 ms | Conforme (hors données) |
| nomenclature | `/nomenclature/{niveau}/{id}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| notifications | `/notifications` | 🟡 20 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 395 ms | Partiel |
| organigramme | `/organigramme` | 🟡 6 nœud(s) | 🟡 mobile 30px | ✅ 200, sans erreur JS | non vérifiée | 🟡 4 627 ms | Partiel |
| paps | `/paps` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 176 ms | Conforme (hors données) |
| paps | `/paps/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 575 ms | Conforme (hors données) |
| paps | `/paps/pilotage` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 395 ms | Partiel |
| paps | `/paps/{pap}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 614 ms | Conforme (hors données) |
| paps | `/paps/{pap}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| paps | `/paps/{pap}/execution-financiere` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| paps | `/paps/{pap}/historiques/{historique}/snapshot` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| paps | `/paps/{pap}/pdf` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| partenaires | `/partenaires` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 537 ms | Conforme (hors données) |
| partenaires | `/partenaires/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 097 ms | Conforme (hors données) |
| partenaires | `/partenaires/{partenaire}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 097 ms | Conforme (hors données) |
| partenaires | `/partenaires/{partenaire}/conventions/create` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| partenaires | `/partenaires/{partenaire}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| permissions | `/permissions` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 518 ms | Partiel |
| plans-apurement | `/plans-apurement/{planApurement}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| postes | `/postes` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 973 ms | Conforme (hors données) |
| postes | `/postes/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 764 ms | Conforme (hors données) |
| postes | `/postes/{poste}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| previsions-recettes | `/previsions-recettes` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 838 ms | Conforme (hors données) |
| previsions-recettes | `/previsions-recettes/create` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 887 ms | Partiel |
| previsions-recettes | `/previsions-recettes/{prevision}` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 887 ms | Partiel |
| previsions-recettes | `/previsions-recettes/{prevision}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| profil | `/profil/notifications` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 954 ms | Conforme (hors données) |
| profils | `/profils` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 233 ms | Conforme (hors données) |
| profils | `/profils/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 601 ms | Conforme (hors données) |
| profils | `/profils/{profil}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| programmes | `/programmes` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 902 ms | Conforme (hors données) |
| programmes | `/programmes/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 619 ms | Conforme (hors données) |
| programmes | `/programmes/{programme}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/arbre/{exercice}` | 🟡 6 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 879 ms | Partiel |
| rbm | `/rbm/cadre-logique/{exercice}` | 🟡 6 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 738 ms | Partiel |
| rbm | `/rbm/cadre-logique/{exercice}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/catalogues/{catalogue}` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 899 ms | Partiel |
| rbm | `/rbm/catalogues/{catalogue}/create` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/catalogues/{catalogue}/{item}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/chaine-resultats/{exercice}` | 🟡 6 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 716 ms | Partiel |
| rbm | `/rbm/ged/{type}/{id}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/historique/{exercice}` | 🟡 6 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 105 ms | Partiel |
| rbm | `/rbm/historique/{niveau}/{id}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/hub` | 🟡 3 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 520 ms | Partiel |
| rbm | `/rbm/import` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 721 ms | Conforme (hors données) |
| rbm | `/rbm/indicateurs/{indicateur}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/matrice/{exercice}` | 🟡 6 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 772 ms | Partiel |
| rbm | `/rbm/pdf/archiver/{exercice}/{jeu}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/pdf/cadre-logique/{exercice}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/pdf/catalogue/{niveau}/{exercice}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/pdf/indicateurs/{exercice}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/pdf/matrice/{exercice}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/pdf/theorie-changement/{exercice}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/suivi-indicateurs` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 985 ms | Partiel |
| rbm | `/rbm/synthese/{exercice}/pdf` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/theorie-changement/{exercice}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/{niveau}/create` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/{niveau}/{id}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| rbm | `/rbm/{niveau}/{id}/indicateurs` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| recettes | `/recettes` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 495 ms | Partiel |
| recettes | `/recettes/{recette}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| recettes-rapports | `/recettes-rapports` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 480 ms | Partiel |
| recettes-rapports | `/recettes-rapports/arrieres.pdf` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| recettes-rapports | `/recettes-rapports/mobilisation.pdf` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| recettes-rapports | `/recettes-rapports/rapport-financier.docx` | 🟡  nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 447 ms | Partiel |
| recettes-rapports | `/recettes-rapports/rapport-financier.pdf` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| recettes-rapports | `/recettes-rapports/{cle}/archiver/{format}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| recommandations | `/recommandations` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 686 ms | Conforme (hors données) |
| recommandations | `/recommandations/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 606 ms | Conforme (hors données) |
| recommandations | `/recommandations/{recommandation}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 606 ms | Conforme (hors données) |
| recommandations | `/recommandations/{recommandation}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| referentiel-financier | `/referentiel-financier` | 🟡 3 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 073 ms | Partiel |
| referentiel-financier | `/referentiel-financier/pdf/ptf` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| referentiel-financier | `/referentiel-financier/pdf/sources` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| referentiel-financier | `/referentiel-financier/pdf/synthese` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| referentiel-financier | `/referentiel-financier/pdf/{cle}/archiver` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| reporting | `/reporting` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 018 ms | Partiel |
| reporting | `/reporting/cockpit` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | 🟡 2 713 ms | Partiel |
| reporting | `/reporting/cockpit/activites-retard/export/{format}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| reporting | `/reporting/cockpit/activites/{activite}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| reporting | `/reporting/historique` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 822 ms | Conforme (hors données) |
| reporting | `/reporting/modeles` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 031 ms | Conforme (hors données) |
| reporting | `/reporting/modeles/create` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| reporting | `/reporting/modeles/{rapportPersonnalise}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| reporting | `/reporting/modeles/{rapportPersonnalise}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| reporting | `/reporting/modeles/{rapportPersonnalise}/generer` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| reporting | `/reporting/planification` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 835 ms | Conforme (hors données) |
| reporting | `/reporting/techniques` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 670 ms | Conforme (hors données) |
| reporting | `/reporting/{type}` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 713 ms | Partiel |
| reporting | `/reporting/{type}/generer` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| reporting | `/reporting/{type}/generer-async` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| risques | `/risques` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 949 ms | Partiel |
| risques | `/risques/cartographie` | 🟡 16 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 917 ms | Partiel |
| risques | `/risques/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 637 ms | Conforme (hors données) |
| risques | `/risques/export/{format}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| risques | `/risques/{risque}` | 🟡 16 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 917 ms | Partiel |
| risques | `/risques/{risque}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| roles | `/roles` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 785 ms | Partiel |
| roles | `/roles/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 759 ms | Conforme (hors données) |
| roles | `/roles/{role}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| sauvegardes | `/sauvegardes` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 664 ms | Conforme (hors données) |
| sources | `/sources` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 764 ms | Conforme (hors données) |
| sources | `/sources/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 221 ms | Conforme (hors données) |
| sources | `/sources/{source}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 221 ms | Conforme (hors données) |
| sources | `/sources/{source}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/aide` | 🟡 55 nœud(s) | 🟡 mobile 2px | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 141 ms | Partiel |
| strategie | `/strategie/aide/manuel.docx` | 🟡  nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 396 ms | Partiel |
| strategie | `/strategie/alignements` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 547 ms | Conforme (hors données) |
| strategie | `/strategie/alignements/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 020 ms | Conforme (hors données) |
| strategie | `/strategie/dashboard/decisionnel` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 890 ms | Conforme (hors données) |
| strategie | `/strategie/export/coherence.pdf` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/export/matrice.pdf` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/export/pilotage.pdf` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/export/synthese.pdf` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/export/{rapport}/archiver.pdf` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/hub` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 633 ms | Conforme (hors données) |
| strategie | `/strategie/indicateurs` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 597 ms | Conforme (hors données) |
| strategie | `/strategie/indicateurs/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 788 ms | Conforme (hors données) |
| strategie | `/strategie/indicateurs/{indicateurStrategique}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 788 ms | Conforme (hors données) |
| strategie | `/strategie/indicateurs/{indicateurStrategique}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/matrice` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 891 ms | Conforme (hors données) |
| strategie | `/strategie/objectifs` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 750 ms | Conforme (hors données) |
| strategie | `/strategie/objectifs/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 675 ms | Conforme (hors données) |
| strategie | `/strategie/objectifs/{objectifStrategique}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 675 ms | Conforme (hors données) |
| strategie | `/strategie/objectifs/{objectifStrategique}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/orientations` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 579 ms | Conforme (hors données) |
| strategie | `/strategie/orientations/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 590 ms | Conforme (hors données) |
| strategie | `/strategie/orientations/{orientation}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 590 ms | Conforme (hors données) |
| strategie | `/strategie/orientations/{orientation}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/plans` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 508 ms | Conforme (hors données) |
| strategie | `/strategie/plans/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 487 ms | Conforme (hors données) |
| strategie | `/strategie/plans/{plan}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 487 ms | Conforme (hors données) |
| strategie | `/strategie/plans/{plan}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/pont-pap` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 697 ms | Conforme (hors données) |
| strategie | `/strategie/pont-rbm` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 704 ms | Conforme (hors données) |
| strategie | `/strategie/portefeuilles` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 615 ms | Conforme (hors données) |
| strategie | `/strategie/portefeuilles/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 588 ms | Conforme (hors données) |
| strategie | `/strategie/portefeuilles/{portefeuille}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 588 ms | Conforme (hors données) |
| strategie | `/strategie/portefeuilles/{portefeuille}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/programmations-annuelles` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 443 ms | Conforme (hors données) |
| strategie | `/strategie/programmations-annuelles/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 699 ms | Conforme (hors données) |
| strategie | `/strategie/programmations-annuelles/{programmationAnnuelle}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 699 ms | Conforme (hors données) |
| strategie | `/strategie/programmations-annuelles/{programmationAnnuelle}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/programmations-pluri` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 507 ms | Conforme (hors données) |
| strategie | `/strategie/programmations-pluri/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 616 ms | Conforme (hors données) |
| strategie | `/strategie/programmations-pluri/{programmationPluriannuelle}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 616 ms | Conforme (hors données) |
| strategie | `/strategie/programmations-pluri/{programmationPluriannuelle}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/programmes` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 600 ms | Conforme (hors données) |
| strategie | `/strategie/programmes/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 547 ms | Conforme (hors données) |
| strategie | `/strategie/programmes/{programmeStrategique}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 547 ms | Conforme (hors données) |
| strategie | `/strategie/programmes/{programmeStrategique}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/projets` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 720 ms | Conforme (hors données) |
| strategie | `/strategie/projets/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 656 ms | Conforme (hors données) |
| strategie | `/strategie/projets/{projet}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 656 ms | Conforme (hors données) |
| strategie | `/strategie/projets/{projet}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/rapport/coherence` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 564 ms | Conforme (hors données) |
| strategie | `/strategie/risques` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 623 ms | Conforme (hors données) |
| strategie | `/strategie/risques/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 615 ms | Conforme (hors données) |
| strategie | `/strategie/risques/{risqueStrategique}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 615 ms | Conforme (hors données) |
| strategie | `/strategie/risques/{risqueStrategique}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| strategie | `/strategie/visions` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 545 ms | Conforme (hors données) |
| strategie | `/strategie/visions/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 653 ms | Conforme (hors données) |
| strategie | `/strategie/visions/{vision}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 653 ms | Conforme (hors données) |
| strategie | `/strategie/visions/{vision}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| structure-budget | `/structure-budget` | 🟡 6 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 743 ms | Partiel |
| structure-budget | `/structure-budget/arbre-complet` | 🟡 5 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 822 ms | Partiel |
| structure-budget | `/structure-budget/import-export` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| structure-budget | `/structure-budget/journal` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 896 ms | Conforme (hors données) |
| structure-budget | `/structure-budget/lignes/{ligne}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| structure-budget | `/structure-budget/lignes/{ligne}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| structure-budget | `/structure-budget/recherche` | 🟡 3 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 933 ms | Partiel |
| structure-budget | `/structure-budget/structure/{structure}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| structure-budget | `/structure-budget/tableau-bord` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | 🟡 6 083 ms | Conforme |
| structure-budget | `/structure-budget/wizard` | 🟡 5 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 107 ms | Partiel |
| structures | `/structures` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 195 ms | Conforme (hors données) |
| structures | `/structures/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 613 ms | Conforme (hors données) |
| structures | `/structures/export/annuaire.pdf` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| structures | `/structures/{structure}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 704 ms | Conforme (hors données) |
| structures | `/structures/{structure}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| suivi | `/suivi` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 7 431 ms | Conforme (hors données) |
| suivi | `/suivi/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 856 ms | Conforme (hors données) |
| suivi | `/suivi/{campagne}/export/collectes/{format}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| suivi | `/suivi/{campagne}/export/collectes/{format}/archiver` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| suivi | `/suivi/{suivi}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 856 ms | Conforme (hors données) |
| suivi-evaluation | `/suivi-evaluation` | 🟡 3 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 864 ms | Partiel |
| suivi-evaluation | `/suivi-evaluation/evaluations/{evaluation}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| suivi-evaluation | `/suivi-evaluation/missions` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 158 ms | Conforme (hors données) |
| suivi-evaluation | `/suivi-evaluation/missions/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 910 ms | Conforme (hors données) |
| suivi-evaluation | `/suivi-evaluation/missions/{mission}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 910 ms | Conforme (hors données) |
| suivi-evaluation | `/suivi-evaluation/plans` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 530 ms | Conforme (hors données) |
| suivi-evaluation | `/suivi-evaluation/plans/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 016 ms | Conforme (hors données) |
| suivi-evaluation | `/suivi-evaluation/plans/{plan}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 1 016 ms | Conforme (hors données) |
| suivi-financier | `/suivi-financier` | 🟡 1 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 4 025 ms | Partiel |
| suivi-financier | `/suivi-financier/anomalies` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 834 ms | Conforme (hors données) |
| tableaux-bord | `/tableaux-bord` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | ✅ 1 845 ms | Conforme |
| tableaux-bord | `/tableaux-bord/tresorerie` | 🟡 2 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | ✅ concordance vérifiée | 🟡 3 094 ms | Partiel |
| utilisateurs | `/utilisateurs` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 3 656 ms | Conforme (hors données) |
| utilisateurs | `/utilisateurs/create` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 681 ms | Conforme (hors données) |
| utilisateurs | `/utilisateurs/{utilisateur}` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | ✅ 681 ms | Conforme (hors données) |
| utilisateurs | `/utilisateurs/{utilisateur}/edit` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| utilisateurs | `/utilisateurs/{utilisateur}/perimetres` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| verifier | `/verifier/ged/{uuid}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| verifier | `/verifier/rapport/{token}` | — | — | aucun exemple de paramètre | non vérifiée | — | Non vérifiée |
| workflow | `/workflow/audit` | ✅ 0 violation | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 487 ms | Conforme (hors données) |
| workflow | `/workflow/dashboard` | 🟡 6 nœud(s) | ✅ 4 formats | ✅ 200, sans erreur JS | non vérifiée | 🟡 2 641 ms | Partiel |
| workflow | `/workflow/dashboard/export` | — | — | non atteinte | non vérifiée | — | Non vérifiée |
| workflow | `/workflow/validations` | ✅ 0 violation | 🟡 mobile 20px | ✅ 200, sans erreur JS | non vérifiée | ✅ 677 ms | Partiel |

## Synthèse

- Vues évaluées : 358 · **conformes (contrôles UI)** : 188 · **partielles** : 48 · **non vérifiées** : 122.
- « Conforme (hors données) » : interface conforme ; la concordance des données affichées n'a pas été vérifiée pour cette vue précise.
- La performance indiquée est le temps de chargement complet mesuré au navigateur sur le poste local, pendant des campagnes d'audit concurrentes : elle sert de repère, pas de mesure de production.
