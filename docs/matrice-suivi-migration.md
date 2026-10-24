# Matrice de suivi de la migration

Légende statut : `fait` · `partiel` · `api-seule` · `blade` · `reste`.

| Fonctionnalité initiale | API | Écran frontend | Permissions (backend) | Tests | Statut |
|---|---|---|---|---|---|
| Connexion session | `POST /api/v1/session/login` | `/connexion` | compte actif, anti brute-force | `SpaSessionApiTest` | fait |
| MFA TOTP | `POST /api/v1/session/mfa` | `/connexion/mfa` | rôles MFA | `SpaSessionApiTest`, `MfaTest` | fait |
| Déconnexion SPA | `POST /api/v1/session/logout` | bouton layout | authentifié | `SpaSessionApiTest` | fait |
| Profil / périmètre | `GET /api/v1/me` | store Pinia | `auth:sanctum` | `SpaSessionApiTest` | fait |
| Navigation par profil | `GET /api/v1/navigation` | sidebar | permissions menu | `MenuNavigationTest`, `SpaSessionApiTest` | fait |
| Tableau de bord | `GET /api/v1/tableau-bord` | `/tableau-bord` | `tableau_bord.voir` / `reporting.view` / admin | `SpaSessionApiTest` | fait |
| Exercices | `GET /api/v1/exercices` (PAT), `/exercices/hub`, CRUD + ouvrir/clôturer/activer | `/exercices`, `/exercices/nouveau` | `exercice.*` + policies | `ExerciceSpaApiTest`, `SpaSessionApiTest` | fait |
| Structures org. | `GET /api/v1/structures` (PAT), `/structures/hub`, CRUD | `/structures`, `/structures/nouveau` | `structure.*` / `administratif.*` + périmètre | `StructureSpaApiTest` | fait |
| Jetons interop | `POST /api/v1/token` | — (pas SPA) | `api.create` | `ApiTest` | api-seule |
| Structure du budget (liste, détail, soumettre/valider/rejeter, import/export) | `/api/v1/structure-budget/*`, `/import-export` | `/structure-budget`, `/structure-budget/import-export` | `structure_budget.*` + policies | `StructureBudgetSpaApiTest` | fait |
| Budgets (enveloppes, préparation, simulations, révisions, rapport) | `/api/v1/budgets*`, `/preparations*`, `/simulations*`, `/revisions*`, `/rapport` | `/budgets`, `/preparations`, `/simulations`, `/revisions`, `/rapport` | `budget.*` + `rapport.voir` (exports Blade) | `BudgetSpaApiTest` | fait |
| Budget et crédits (lignes, totaux, CRUD) | `/api/v1/budget/credits*` | `/credits`, `/credits/nouveau` | `ligne_budgetaire.*` + policies | `CreditsSpaApiTest` | fait |
| PAP (enveloppe, workflow, pilotage) | `/api/v1/paps*` | `/paps`, `/paps/pilotage` | `pap.*` + policies | `PapSpaApiTest` | fait |
| Chaîne RBM / GAR (lecture + édition nœuds) | `/api/v1/rbm/hub`, `/api/v1/rbm/vue`, `/api/v1/rbm/niveaux*` | `/rbm`, `/rbm/niveaux/:niveau` | `rbm.voir` / `rbm.creer` / `rbm.modifier` | `RbmSpaApiTest` | fait |
| Expression des besoins (liste, création, détail, soumettre/décider/convertir) | `/api/v1/besoins*` | `/besoins` | `besoin.*` + policies | `DepenseSpaApiTest` | fait |
| Engagements (création depuis besoin, soumettre, visa, crédits) | `/api/v1/engagements*` | `/engagements` | `engagement.*` + `EngagementService` | `DepenseSpaApiTest` | fait |
| Liquidation, ordonnancement, paiement | `/api/v1/depense/{type}` | `/liquidations`, `/ordonnancements`, `/paiements` | `liquidation.*` / `ordonnancement.*` / `paiement.*` | `DepenseAvalSpaApiTest` | fait |
| Rapprochement bancaire | `/api/v1/rapprochements*` | `/rapprochements` | `paiement.valider` / `depenses.admin` | `RapprochementSpaApiTest` | fait |
| Recettes et encaissements | `/api/v1/recettes*` | `/recettes`, `/encaissements` | `recette.*` + policies | `RecettesSpaApiTest` | fait |
| Appels de fonds | `/api/v1/appels-fonds*` | `/appels-fonds` | `recette.voir` / `recette.recouvrement` | `AppelsFondsSpaApiTest` | fait |
| Plans d’apurement | `/api/v1/plans-apurement*` `/api/v1/echeances-apurement*` | `/plans-apurement/:id` | `recette.voir` / `recette.recouvrement` | `PlanApurementSpaApiTest` | fait |
| États des recettes | `/api/v1/recettes/etats` | `/recettes/etats` | `recette.voir` / `recette.exporter` | `RecettesEtatsSpaApiTest` | fait |
| GED (catalogue, dépôt, signatures) | `/api/v1/ged/hub`, `/api/v1/ged/documents*`, `/api/v1/ged/signatures*` | `/ged`, `/ged/depot`, `/ged/signatures` | `ged.voir` / `ged.creer` / `ged.signer` + `GedAccessService` | `GedSpaApiTest` | fait |
| Workflow (performance, corbeille, décision, admin circuits) | `/api/v1/workflow/hub`, `/api/v1/workflow/admin/circuits*` | `/workflow`, `/workflow/corbeille`, `/workflow/circuits` | `workflow.view` / `validate` / `admin` | `WorkflowSpaApiTest` | fait |
| Reporting (lecture catalogue / historique) | `/api/v1/reporting/hub`, `/api/v1/reporting/rapports*` | `/reporting`, `/reporting/rapports` | `reporting.view` / `rapport.voir` | `ReportingSpaApiTest` | fait |
| TOMPRO | `/api/v1/tompro/*` | — | ability `tompro` | `TomproExtendedTest` | api-seule |
| Notifications et alertes (centre, préférences, résolution) | `/api/v1/notifications*` | `/notifications`, `/notifications/alertes`, `/notifications/preferences` | `notifications.view` / `manage` / `alertes.view` / `resolve` | `NotificationsSpaApiTest` | fait |
| Administration / habilitations | `GET /api/v1/utilisateurs/hub`, CRUD, statut, périmètre | `/utilisateurs` | `utilisateur.*` + policies | `AdminUtilisateursSpaApiTest` | fait |
| Paramétrage MFA (compte) | `GET|POST|DELETE /api/v1/session/mfa/setup` | `/securite/mfa` | session authentifiée | `MfaSetupSpaApiTest` | fait |
| Rôles / profils / journal connexions | — | Blade (liens depuis hub) | `role.*` / `profil.*` | tests rôles | blade |

Les montants du tableau de bord SPA sont sérialisés via `Montant` (`tests/Unit/MontantTest.php`).
