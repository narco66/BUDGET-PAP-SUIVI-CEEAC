# Conventions API v1

Préfixe : `/api/v1`. Contrat : `resources/openapi/v1.json`.

## Identifiants

- Entiers internes (`id`) pour les ressources Eloquent.
- UUID pour les documents GED (`document:uuid`).
- Codes métier (`exercice.code`, `structure.code`) acceptés en filtre lorsqu’indiqué.

## Dates

- Dates calendaires : `YYYY-MM-DD`.
- Horodatages : ISO-8601 (`toIso8601String()`).
- Fuseau : celui de l’application (`APP_TIMEZONE`, défaut Laravel).

## Montants et devise

- Devise institutionnelle : **XAF**.
- Représentation JSON : **chaîne** à point décimal, deux décimales (`"1250000.50"`), jamais un `number` IEEE-754.
- Backend : `App\Support\Montant::api()`.
- Frontend : parser en décimal affiché (`fr-FR`) sans reconvertir en `number` pour des calculs de contrôle de crédits.

## Null

- Absence de valeur : `null`.
- Collection vide : `[]`.
- Ne pas omettre un champ obligatoire du contrat.

## Pagination

Format Laravel Resource : `{ "data": [], "links": {}, "meta": {} }`.
`per_page` plafonné (souvent 100).

## Erreurs

```json
{ "message": "…", "code": "invalid_credentials", "errors": { "email": ["…"] } }
```

| HTTP | Usage |
|---|---|
| 401 | Non authentifié / MFA expiré |
| 403 | Permission, scope ou compte inactif |
| 404 | Ressource hors périmètre ou inconnue |
| 419 | CSRF manquant (SPA cookies) |
| 422 | Validation métier |
| 429 | Débit ou IP bloquée |

## Authentification

1. **SPA** : `GET /sanctum/csrf-cookie` puis `POST /api/v1/session/login` avec cookies + `X-XSRF-TOKEN`. Middleware `ability.or.session`.
2. **Interop** : `POST /api/v1/token` → `Authorization: Bearer`. Scopes `lecture`, `suivi`, `tompro`.

Les contrôles d’objet (Policy, `AccessScopeService`) s’appliquent dans les deux modes.

## CRUD vs actions métier

Les transitions (soumettre, valider, rejeter, décider un workflow) sont des `POST` d’action, pas des `PATCH` génériques de statut.

## Idempotence et concurrence

- En-tête `Idempotency-Key` : une clé rejouée après un succès (2xx) renvoie la même réponse sans second effet.
- En-tête `If-Match` : valeur `version` (ISO-8601 `updated_at`) de la ressource. Écart → **409** `conflict`.
- Les transitions de ligne sont exécutées dans une transaction avec `lockForUpdate`.
- La soumission d’engagement réserve les crédits (`EngagementService::reserverCredits`). Un disponible insuffisant renvoie **422** `credits_ou_transition`. Le rejeu de la même `Idempotency-Key` ne réserve pas deux fois.
- Une liquidation ne peut pas dépasser le restant de l’engagement visé (`DepenseQuotaService`) : **422** `credits_ou_transition`. Les compteurs de ligne ne sont incrémentés qu’à la validation du circuit (`compteurs_appliques`).
- Le rapprochement automatique lie un paiement validé à une ligne de relevé (montant ± 0,05 XAF et référence si présente). La clôture exige zéro ligne `non_rapproche`.
- PAP : `version` est le numéro métier de l’enveloppe (entier) ; `If-Match` utilise `etag` (`updated_at` ISO-8601). La soumission est refusée (**422** `audit_bloquant`) s’il n’y a aucune activité RBM sur l’exercice.
- Budget enveloppe : `GET /budgets`, `GET /budgets/options`, `POST /budgets`, `PUT /budgets/{id}`, `POST …/soumettre`, `POST …/decisions`, `POST …/revision`. Champ `montant` en chaîne ; `montant_total` conservé (nombre) pour l’interop PAT. Soumission **422** `audit_bloquant` si l’audit signale des anomalies.
- Préparation budget : `GET /budgets/{id}/preparations`, `POST …/initialiser`, `PUT …/preparations/{id}`, `POST …/soumettre`, `…/valider`, `…/arbitrer`, `…/consolider`. Montants en chaînes.
- Simulations budget : `GET /budgets/{id}/simulations?comparer[]=`, `POST …/simulations`, `POST …/simulations/{id}/calculer`, `POST …/retenir`. Montants et écarts en chaînes.
- Révisions budget : `GET /budgets/{id}/revisions`, `POST …/rectificatif`, `POST …/virement`, `POST …/lier-pluri`. Montants en chaînes. Budget adopté ou en révision requis (`rectifier`).
- Rapport enveloppe : `GET /budgets/{id}/rapport`. Lecture `budget.voir` (ou `ligne_budgetaire.voir`). Montants en chaînes. PDF/Excel : liens Blade `reporting.generer` si `rapport.voir`.
- Budget et crédits : `GET /budget/credits`, `GET …/options`, `POST /budget/credits`, `PUT|DELETE …/{ligne}`. Lecture `ligne_budgetaire.voir`. Montants en chaînes. Import/export Excel : liens Blade. Exercice verrouillé (enveloppe en validation / adoptée) → **422** `transition_invalide`.
- Exercices : `GET /exercices` (PAT, liste simple) inchangé. SPA : `GET /exercices/hub`, `GET|PUT|DELETE /exercices/{id}`, `POST /exercices`, `POST …/ouvrir|cloturer|activer`. Lecture `exercice.voir`. Clôture d’un brouillon → **422** `transition_invalide`.
- Structures org. : `GET /structures` (PAT) inchangé. SPA : `GET /structures/hub`, `GET /structures/options`, `GET|PUT|DELETE /structures/{id}`, `POST /structures`. Lecture `structure.voir` (alias `administratif.voir`) + périmètre. Suppression d’un nœud avec enfants → **422** `transition_invalide` (soft delete). Excel/PDF/import restent Blade.
- Session SPA : `POST /session/logout` invalide la session. `POST /session/mfa` après `mfa_required`. Paramétrage : `GET|POST|DELETE /session/mfa/setup` (QR en data URI, codes de secours une fois). Désactivation refusée si le rôle est dans `MFA_REQUIRED_ROLES` → **422** `transition_invalide`.
- Utilisateurs : `GET /utilisateurs/hub`, `GET /utilisateurs/options`, `GET|PUT|DELETE /utilisateurs/{id}`, `POST /utilisateurs`, `POST …/statut`, `POST …/reinitialiser`, `PUT …/perimetres`. Lecture `utilisateur.voir`. Auto-changement de statut → **422**. Mot de passe temporaire renvoyé une fois à la réinitialisation. Rôles / profils / journal restent Blade.
- Encaissement : soumission **422** `transition_invalide` sans justificatif ou sans rubrique sur la recette. La dernière étape du circuit RECETTES comptabilise `montant_realise`.
- Appel de fonds : un règlement cumulé supérieur au montant appelé → **422** `transition_invalide`. Annulation interdite dès qu’un règlement existe.
- Plan d’apurement : un seul plan actif par appel ; le règlement d’échéance est reporté sur l’appel. Dépassement de l’échéance → **422** `transition_invalide`. Annulation interdite dès qu’un règlement existe.
- États des recettes : `GET /recettes/etats?jeu=` (`mobilisation`, `arrieres`, `encaissements`, `previsions`, `financier`). Lecture `recette.voir` ; liens d’export Blade uniquement si `recette.exporter`. Jeu inconnu → **422** `jeu_invalide`.
- RBM/GAR : `GET /rbm/hub`, `GET /rbm/vue?jeu=`, `POST|PUT /rbm/niveaux/{niveau}`. Lecture `rbm.voir`. Création `rbm.creer`, édition `rbm.modifier`. Montants en chaînes. Export Excel/PDF RBM : liens Blade. PAP verrouillé → **422** `transition_invalide`.
- Structure budget import/export : `GET /structure-budget/import-export` (liens Blade xlsx/csv/pdf, pas de binaire JSON). `POST /structure-budget/import/apercu` (multipart) puis `confirmer` / `annuler` (fichier temporaire 1 h). Import `structure_budget.importer` ou `creer`. Exercice verrouillé → **422** `transition_invalide`.
- GED : `GET /ged/hub`, `GET /ged/documents` / `{uuid}`, `POST /ged/documents` (multipart), `GET /ged/signatures`, `POST …/signatures`. Lecture `ged.voir` + `GedAccessService`. Dépôt `ged.creer`. Signature `ged.signer`. Métadonnées uniquement en JSON (pas de `chemin_fichier` ni certificat). Téléchargement binaire reste Blade.
- Reporting : `GET /reporting/hub` et `GET /reporting/rapports` / `{id}`. Lecture `reporting.view` (alias `rapport.voir`). Pas de fichier binaire. Génération / signature / PDF restent Blade.
- Workflow : `GET /workflow/hub`, `GET /workflow/instances`, `POST /workflow/instances/{id}/decisions`. Lecture `workflow.view` ; décision `workflow.validate`. Admin circuits : `GET|POST /workflow/admin/circuits`, `PUT …/{id}`, `POST …/{id}/toggle` (`workflow.admin` / `create` / `update`). `GET /workflow/circuits` (PAT, actifs uniquement) inchangé.
- Notifications : `GET /notifications/hub`, `GET /notifications`, `POST …/{id}/read`, `POST /notifications/tout-lu`, `POST …/{id}/archiver`. Lecture `notifications.view` ; archivage / préférences `notifications.manage`. Alertes : `GET /notifications/alertes`, `POST …/alertes/{id}/resolve` (`alertes.view` / `alertes.resolve`). Export CSV et modèles e-mail restent Blade.
