# 09 — Workflow transversal

## Cible (procédures 2026)

EB approuvée → ENG auto → Direction Budget → CF → LIQ auto → certification service fait → CF → ORD auto → SG (≤ seuil) ou Président (> seuil) → PAY (Agence comptable) → preuve / rapprochement.

## État après incrément aval (24/10/2026)

| Transition | Implémentation |
|---|---|
| EB → ENG | Auto unique à l’approbation |
| ENG → LIQ | Auto unique au visa CF |
| LIQ → ORD | Auto unique après certification + visa CF |
| ORD → PAY | OP émis + dossier PAY `en_preparation` |
| ORD compétence | SG ≤ 5 M ; Président > 5 M (paramètre versionné) ; délégation et intérim organiques |
| EB PAP appui | `BESOIN_PAP_APPUI` (Directeur → SG) si structure d’appui (`SG`, `PRES`, types configurés) |
| Clôture | Persistée si soldée ; rouverte (horodatage nul) après contrepassation / dégagement |

## État initial (audit)

| Transition | Implémentation |
|---|---|
| EB → ENG | Manuelle (`convertir` + store) |
| ENG → LIQ | Manuelle |
| LIQ → ORD | Manuelle |
| ORD → PAY | OP auto ; Paiement manuel |
| Rejet / retour | Workflow générique |

## Invariants à préserver

`payé ≤ ordonnancé ≤ liquidé ≤ engagé` ; réservation uniquement à l’ENG ; transitions auto idempotentes dans une transaction.

Une panne de notification est journalisée (`echec_reprise`) et n’annule pas une décision financière déjà persistée.

Le QR de fiche pointe vers une URL authentifiée : pas de portail public inventé. Le seuil ORD snapshoté n’est pas recalculé après un changement de paramètre.

Le cockpit d’exécution (`GET /api/v1/depense-cockpit`, SPA `/depense`) réutilise `TableauBordService` / `DepenseReportingService` / `DepenseAuditService` (audit non persisté). Droit `engagement.voir`. Exports Excel/CSV session : `GET /api/v1/depense-exports/{cle}` (mêmes jeux que Blade). Vérification QR SPA `/verification/{type}/{id}` (session, pas de portail public). Dossier unifié `/dossiers/{besoin|engagement}/{id}`. Consolidation D1 `/besoins/consolidation` (`besoin.valider`). Marchés / Contrats : SPA `/marches` `/contrats` (CRUD session, mêmes permissions Blade).
