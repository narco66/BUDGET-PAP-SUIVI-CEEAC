# 02 — Rapport d’audit global

**Périmètre :** EB → ENG → LIQ → ORD → PAY selon les procédures v1.0 du 24/10/2026.  
**Méthode :** lecture des cinq MD, confrontation au code Laravel/Vue, aux circuits `WorkflowSeeder`, aux migrations et aux tests PHPUnit existants.  
**Aucune modification métier n’a été faite pendant cet audit.**

## Synthèse

La chaîne **existe et est opérationnelle** au sens du manuel D0–D5 : brouillon → soumission workflow → visas, réservation de crédits `lockForUpdate`, compteurs `montant_engage/liquide/ordonnance/paye`, quotas de liquidation, rapprochement, GED polymorphe, PDF internes, API SPA idempotente (`Idempotency-Key`, `If-Match`).

Elle **n’est pas conforme** au référentiel 24/10/2026 sur les points qui conditionnent la régularité : **double circuit PAP/hors PAP**, **sous-lignes EB**, **transitions automatiques uniques**, **étape Direction du Budget**, **seuil d’ordonnancement versionné**, **distinction émission vs exécution du paiement**, **Agent comptable**, **lignes de liquidation / retenues**, **Playwright / Figma**.

**Niveau global vis-à-vis des 5 MD :** partiellement conforme (socle technique) / **non conforme** sur les circuits et l’intégration automatique.

## Frontend

- Pages Vue : listes, création, détail, soumission/décision pour EB, ENG, LIQ, ORD, PAY.
- Pas de distinction PAP / hors PAP à la saisie (activité PAP **obligatoire** dans `BesoinNouveauView`).
- Pas de sous-lignes, pas d’héritage programmatique lecture seule, pas de tableau de bord dédié par file d’attente commissaire/SG/Président.
- PDF/GED : liens Blade hérités pour binaires, pas d’aperçu QR officiel.
- Boutons conditionnés par permissions API (`actions` des resources) — pas de boutons totalement fictifs sur ces parcours, mais **absence** d’actions prévues par la spec (signer Président, certifier service fait comme étape distincte du visa CF, paiement partiel guidé).

## Backend

- Validations serveur : FormRequest + policies + `WorkflowService`.
- Idempotence et conflit 409 : présents sur les actions SPA.
- Réservation crédits ENG : atomique à la **soumission**, pas à l’approbation EB (conforme à EB-REQ « l’approbation EB n’est pas la consommation »).
- Transitions auto EB→ENG, ENG→LIQ, LIQ→ORD : **absentes**. `POST besoins/{id}/convertir` ne crée pas l’ENG.
- `OrdrePaiementService::emettre` à la validation ORD : idempotent, mais le **Paiement** reste une création manuelle.
- Insuffisance de crédit : exception bloquante, **pas** de circuit de réallocation/virement.

## Données

- Tables : `expressions_besoin` (montant unique, pas de lignes), `engagements`, `engagement_imputations`, `reservations_credits`, `liquidations`, `ordonnancements`, `paiements`, `paiement_details`, `paiement_rejets`, `ordres_paiement`, `services_faits`.
- PK entières, `decimal(18,2)` pour les montants (pas float) — **conforme à l’esprit** « décimal exact », **non conforme** au modèle conceptuel UUID.
- Pas de table `parametres_seuil_ordonnancement` versionnée.
- Invariant payé≤ordonnancé≤liquidé≤engagé : CHECK / services existants (audit juin).

## Sécurité

- Auth Sanctum session + abilities.
- Permissions `besoin.*`, `engagement.*`, etc.
- SoD workflow par **rôle Spatie de l’étape**, pas par position organique + affectation au dossier au niveau exigé par la spec §9.
- Périmètre `AccessScopeService` sur les listes métier.
- Auto-validation : le workflow empêche en principe le mauvais rôle ; **pas** de contrôle explicite « l’initiateur ne vise pas » au-delà du rôle.
- Contournement `depenses.admin` : permet un ENG sans besoin — **écart de régularité** vis-à-vis de la spec (à conserver seulement comme exception administrateur documentée).

## GED / PDF

- Pièces via `HasPieces`.
- PDF `pdf/besoin.blade.php`, `pdf/engagement.blade.php`, `pdf/etape_depense.blade.php` : fiches internes, **sans** comparaison possible aux modèles Figma (absents).
- SHA-256 GED existant côté module GED, pas forcément lié à chaque version signée EB–PAY.

## Tests

- `DepenseTest`, `DepenseD0`–`D5`, `DepenseAval`, `DepenseSpaApiTest`, `DepenseAvalSpaApiTest`, `RapprochementSpaApiTest`.
- **Pas** de tests du circuit PAP vs hors PAP, du seuil 5 M, de la concurrence de deux paiements sur le même ORD, ni Playwright.

## Hypothèses configurables (non bloquantes)

- H1 : stack Vue conservée.
- H2 : identifiants numériques conservés.
- H3 : moteur `Workflow` existant étendu (nouveaux codes `BESOIN_PAP`, `BESOIN_HORS_PAP`, `ENGAGEMENT` à 2 étapes) plutôt qu’un second moteur.

## Ambiguïtés **non** tranchées (risque financier)

- Assiette du seuil ORD.
- Mapping DRHMG / Directeur du Budget / Agent comptable sur les rôles Spatie.
- Moment exact de la réservation de crédit si les textes imposent une réservation dès l’EB (le dossier EB dit le contraire : réservation à l’ENG).
