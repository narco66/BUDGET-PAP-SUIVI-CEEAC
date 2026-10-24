# 13 — Tests et recette

## Existant

PHPUnit : `DepenseTest`, D0–D5, `DepenseSpaApiTest`, `DepenseAvalSpaApiTest`, `RapprochementSpaApiTest`.

## Manquant (à compléter module par module)

- EB PAP / hors PAP, sous-lignes, ENG unique, concurrence soumission.
- ENG Direction Budget + LIQ auto + insuffisance.
- LIQ service fait + partielles.
- ORD seuil / Président / anti-fractionnement.
- PAY partiel concurrent, émission vs exécution.
- Playwright E2E : **non présent** ; recette UI via SPA locale si serveur up.

## Résultats

- 24/10/2026 — EB : `php artisan test --filter="ChaineDepenseEbConformiteTest|DepenseSpaApiTest"` → 8 passed.
- 24/10/2026 — Aval : `ChaineDepenseAvalConformiteTest` (LIQ auto, service fait, seuil, paiement partiel).
- 24/10/2026 — EB retour / suppression + LIQ tranches / doublon facture : relancer `ChaineDepenseEbConformiteTest|ChaineDepenseAvalConformiteTest`.
- 24/10/2026 — Clôture, dégagement, motif partiel, QR interne, rapprochement ≠ visa : `ChaineDepenseEbConformiteTest|ChaineDepenseAvalConformiteTest|DepenseAvalSpaApiTest|DepenseSpaApiTest` → **28 passed**.
- 24/10/2026 — Contrepassation, champs par mode, EB obligatoire, exercice clos : `ChaineDepenseEbConformiteTest|ChaineDepenseAvalConformiteTest|DepenseAvalSpaApiTest|DepenseSpaApiTest|DepenseD0Test` → **40 passed**.
- 24/10/2026 — Catégorie figée, PUT `a_corriger`, brut/net LIQ, structure Budget : même filtre → **43 passed**.
- 24/10/2026 — PAP appui, exercice clos, convertir idempotent, intérim SG, If-Match, QR LIQ : **59 passed**.
- 24/10/2026 — Notifications isolées, EB soumis figé, If-Match EB, QR session, seuil non rétroactif, SoD PAY : **65 passed**.
- 24/10/2026 — Recette SPA locale + PDF session (besoins/engagements/étapes) + filtres `a_corriger` / catégorie : même filtre → **65 passed**.
- 24/10/2026 — Pièces dossier EB + vérification API session : même filtre → **66 passed**.
- 24/10/2026 — Cockpit SPA : `CockpitDepenseTest` (Blade + `GET api.depense.cockpit` montants décimaux / 403) ; filtre chaîne + cockpit → **69 passed**.
- 24/10/2026 — Exports dépense API + page vérification SPA ; filtre chaîne + cockpit → **70 passed**.
- 24/10/2026 — QR SPA, dossier unifié, consolidation D1, visa RIB indépendant ; filtre chaîne → **74 passed**.
- 24/10/2026 — Marchés / Contrats SPA (`GET/POST/PUT/DELETE /api/v1/marches|contrats`, montants `5000000.00`, 403 lecteur) : `MarchesContratsTest`.
- 24/10/2026 — Suivi SPA (campagnes, reco, formations, immobilisations) : `SuiviTest`, `RecommandationsTest`, `FormationsTest`, `ImmobilisationsTest`.
- 24/10/2026 — Rapports personnalisés + suivi financier SPA : `RapportPersonnaliseTest`, `SuiviP3Test`.
- 24/10/2026 — Cockpit exécutif + planification SPA : `ReportingSpaApiTest::test_api_cockpit_et_planification`.
- 24/10/2026 — GED recherche + wiki SPA : `GedSpaApiTest`, `ConnaissanceTest::test_api_session_wiki_et_procedures`.
- 24/10/2026 — GED classement / vues / référentiel SPA : `GedSpaApiTest::test_api_classement_vues_et_referentiel`.
- 24/10/2026 — Audit workflow SPA : `WorkflowSpaApiTest::test_audit_interdit_sans_droit_autorise_pour_auditeur` (`pap.voir` seul 403 / auditeur 200, sans ip).
- 24/10/2026 — Modèles e-mail SPA : `NotificationsEmailP2Test::test_api_templates_interdit_et_maj` (chef 403 / admin PUT, liste sans `corps_html`).
- 24/10/2026 — Hub interopérabilité SPA : `ConnectedAppsP1Test::test_api_hub_interdit_et_stats` (chef 403 / admin KPI, sans secret).
- 24/10/2026 — Applications connectées SPA : `ConnectedAppsP1Test::test_api_apps_crud_secret_une_fois`.
- 24/10/2026 — Journal API SPA : `ConnectedAppsP1Test::test_api_journal_interdit_et_filtre` (chef 403, sans user-agent / hash).
- 24/10/2026 — Webhooks SPA : `WebhooksP2Test::test_api_webhooks_interdit_et_sans_secret`.
- 24/10/2026 — Monitoring API SPA : `ApiMonitoringP3Test::test_api_monitoring_interdit_et_kpis`.
