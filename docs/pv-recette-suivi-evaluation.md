# Procès-verbal de recette MOA
## Module Suivi-Évaluation — BUDGET-PAP-SUIVI-CEEAC

**Document type :** Procès-verbal de recette fonctionnelle  
**Version du modèle :** 1.0 — Juillet 2026  
**Application :** BUDGET-PAP-SUIVI-CEEAC  
**Module recetté :** Suivi-Évaluation (P0 → P3)

---

> **Mode d'emploi :** copiez ce contenu dans Microsoft Word, appliquez la charte graphique CEEAC (en-tête, pied de page, numérotation), exportez en PDF pour signature. Les champs entre `[crochets]` sont à compléter.

---

| **Référence PV** | PV-REC-SE-2026-01 |
|------------------|-------------------|
| **Date de recette** | 09/07/2026 *(mercredi — session planifiée)* |
| **Lieu** | Siège CEEAC — Libreville — Bâtiment Administratif — **Salle Planification & Suivi-évaluation** (1er étage, aile Ouest) |
| **Exercice testé** | 2026 |
| **Version applicative** | [ex. v2.5.0 / commit …] |
| **Environnement** | ☑ Préproduction   ☐ Production   ☐ Autre : _________ |

---

## 1. Objet du procès-verbal

Le présent procès-verbal atteste de la recette fonctionnelle du **module Suivi-Évaluation** de l'application **BUDGET-PAP-SUIVI-CEEAC**, réalisée sous la responsabilité de la Maîtrise d'Ouvrage (MOA), représentée par la **Direction Planification & Suivi-évaluation** et le **Contrôle financier** de la Commission de la CEEAC.

Il constate, après exécution des scénarios de test définis ci-après, que le dispositif permet :

- le **pilotage institutionnel S&E** via le hub `/suivi-evaluation` (scores agrégés, niveaux de performance) ;
- le calcul unifié des scores via **`SuiviEvaluationCalculator`** (physique PAP, IOV, recommandations, formations, conformité) ;
- la génération et l'**historisation des évaluations périodiques** (`evaluations_periodiques`) ;
- la **capture automatique** à la clôture des campagnes de suivi physique ;
- la gestion des **missions de suivi**, **plans d'amélioration** et **actions correctives** ;
- le lien **recommandations ↔ évaluations** (catégories, rattachement optionnel) ;
- la production du **rapport institutionnel S&E** et l'accès **API REST** ;
- le respect des permissions `suivi_evaluation.*` et de la traçabilité audit.

---

## 2. Contexte et périmètre

### 2.1 Contexte

La Commission de la CEEAC a engagé le renforcement du dispositif de **Suivi-Évaluation institutionnel** via l'application intégrée **BUDGET-PAP-SUIVI-CEEAC**. Un audit de conformité (juin 2026) a identifié un score global ~48 % et un plan de correction en quatre phases (P0 à P3), désormais implémenté.

### 2.2 Périmètre recetté

| Inclus | Exclus |
|--------|--------|
| Calcul scores S&E unifié — `SuiviEvaluationCalculator` (P0) | Module missions internationales hors CEEAC |
| Snapshots `evaluations_periodiques`, permissions `suivi_evaluation.*` (P0) | Évaluations d'impact externe (P4+) |
| Rapport `suivi_evaluation`, commande `suivi-evaluation:capture` (P1) | Migration historique antérieure à [AAAA] |
| Missions `missions_suivi`, plans `plans_amelioration`, actions (P2) | Personnalisation hors spec validée |
| API REST `/api/v1/suivi-evaluation/*`, seeder démo (P3) | |

### 2.3 Références

| Réf. | Document |
|------|----------|
| [AUD-SE-2026] | Rapport d'audit module Suivi-Évaluation |
| [DOC-SE] | `docs/module-suivi-evaluation.md` |
| [DOC-API] | `docs/api-suivi-financier-indicateurs.md` (§ Suivi-Évaluation) |
| [CONV-DEMO] | `docs/convocation-demo-DEMO-SE-2026-01.md` — démo 03/07/2026 |
| [CR-DEMO] | `docs/compte-rendu-demo-DEMO-SE-2026-01.md` — CR-DEMO-SE-2026-01 |
| [CHECK-DEMO] | `docs/checklist-animateur-DEMO-SE-2026-01.md` |
| [CHECK-REC] | `docs/checklist-animateur-PV-REC-SE-2026-01.md` |
| [CONV-REC] | `docs/convocation-recette-PV-REC-SE-2026-01.md` |
| [ANNUAIRE] | `docs/annuaire-recette-suivi-evaluation-2026.md` |
| [PV-SF] | `docs/pv-recette-suivi-financier.md` *(recette modules connexes)* |

---

## 3. Composition de la commission de recette

| Rôle | Nom et prénom | Fonction | Structure | Signature |
|------|---------------|----------|-----------|-----------|
| **Présidente de séance — MOA** | Sylvie **NGUEMA ADZOVÉ** | Resp. PAP / Suivi-évaluation | Direction Planification & Suivi-éval. | |
| Membre | Paul-André **MBENG MBA** | Contrôleur financier | Direction du Contrôle financier | |
| Membre | Grace **ONDO** | Gestionnaire budgétaire | Direction du Budget | |
| Membre | Daniel **ESSAME ESONO** | Secrétaire général | Secrétariat Général | |
| Représentant MOE / DSI | Jean-Claude **NKOGHE NDONG** | Chef de projet SI | Direction des Systèmes d'Information | |
| Représentant MOE | Arsène **BIKANG** | Intégrateur applicatif | DSI — Projet BUDGET-PAP | |
| Secrétariat de séance | Chantal **MBOUMBA** | Assistante Contrôle financier | Direction du Contrôle financier | |

---

## 4. Environnement et données de test

| Élément | Valeur constatée |
|---------|------------------|
| URL de l'application | https://budget-pap-preprod.ceeac.int |
| Base de données | MySQL — serveur préprod `srv-preprod-dsi.ceeac.int` |
| Exercice budgétaire | 2026 |
| Jeu de données | ☐ Données réelles anonymisées   ☑ `SafeDatabaseSeeder` *(incl. `SuiviEvaluationDemoSeeder`)*   ☐ Mixte |
| Comptes utilisateurs testés | s.nguema / commissaire / chef_service / auditeur / administrateur |
| Date du dernier déploiement | [JJ/MM/AAAA] |
| Contact secrétariat recette | Chantal MBOUMBA — c.mboumba@ceeac.int — +241 01 44 47 22 |
| Migrations exécutées | `php artisan migrate` → ☐ OK   ☐ KO |
| Permissions synchronisées | `php artisan permissions:sync-production --force` → ☐ OK   ☐ KO |
| Tests automatisés | Voir § 4.1 |

### 4.1 Commandes de vérification pré-recette (MOE)

```bash
php artisan migrate
php artisan permissions:sync-production --force
php artisan test tests/Feature/SuiviEvaluationP0Test.php tests/Feature/SuiviEvaluationP1Test.php tests/Feature/SuiviEvaluationP2Test.php tests/Feature/SuiviEvaluationP3Test.php
php artisan suivi-evaluation:capture --exercice=2026
```

| Commande | Résultat attendu | Constat |
|----------|------------------|---------|
| Suite de tests ci-dessus | 100 % vert (20 tests) | ☐ OK   ☐ KO |
| `suivi-evaluation:capture` | ≥ 1 ligne `evaluations_periodiques` | ☐ OK   ☐ KO |

---

## 5. Grille de recette fonctionnelle

**Légende résultat :**  
☐ **C** — Conforme   ☐ **NC** — Non conforme   ☐ **NA** — Non applicable   ☐ **R** — Conforme avec réserve

### Bloc A — Hub et scores agrégés (P0)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| A1 | Accès hub | Menu **Suivi-évaluation** → GET `/suivi-evaluation` — HTTP 200 | ☐ C ☐ NC ☐ R | |
| A2 | Score global | Carte « Score global S&E » affichée avec niveau (satisfaisant / partiel / insuffisant) | ☐ C ☐ NC ☐ R | |
| A3 | Composantes | 5 composantes visibles : physique, IOV, recommandations, formations, conformité | ☐ C ☐ NC ☐ R | |
| A4 | Cohérence calculateur | Scores hub = `SuiviEvaluationCalculator::synthese()` pour même exercice | ☐ C ☐ NC ☐ R | |
| A5 | Filtre exercice | Changement d'exercice → recalcul des KPI | ☐ C ☐ NC ☐ R | |
| A6 | Refus sans permission | Compte sans `suivi_evaluation.view` ni alias → HTTP 403 | ☐ C ☐ NC ☐ R | |

### Bloc B — Évaluations périodiques (P0 / P1)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| B1 | Génération annuelle | POST `/suivi-evaluation/generer` → ligne `evaluations_periodiques` type `annuelle` | ☐ C ☐ NC ☐ R | |
| B2 | Idempotence | Regénération même période → mise à jour sans doublon (contrainte unique) | ☐ C ☐ NC ☐ R | |
| B3 | Détail évaluation | GET `/suivi-evaluation/evaluations/{id}` — scores + métadonnées | ☐ C ☐ NC ☐ R | |
| B4 | Historique hub | Table « Évaluations périodiques enregistrées » avec lien Détail | ☐ C ☐ NC ☐ R | |
| B5 | Capture batch | `php artisan suivi-evaluation:capture --exercice=2026` → succès | ☐ C ☐ NC ☐ R | |
| B6 | JSON détails | Colonne `details` contient composantes et poids | ☐ C ☐ NC ☐ R | |

### Bloc C — Intégration campagnes de suivi (P1)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| C1 | Lien campagnes | Bouton **Campagnes** → `/suivi` filtré exercice 2026 | ☐ C ☐ NC ☐ R | |
| C2 | Clôture campagne | Clôture campagne ouverte → snapshot `evaluations_periodiques` type `campagne` | ☐ C ☐ NC ☐ R | |
| C3 | Score physique | Composante physique reflète couverture PAP + taux collectes validées | ☐ C ☐ NC ☐ R | |
| C4 | Audit conformité | Score conformité = `SuiviAuditService` score sur exercice | ☐ C ☐ NC ☐ R | |
| C5 | Couverture PAP | Carte couverture cohérente avec collectes validées / activités | ☐ C ☐ NC ☐ R | |

### Bloc D — Recommandations (P0 enrichi)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| D1 | Accès registre | Lien **Recommandations** depuis hub → liste exercice 2026 | ☐ C ☐ NC ☐ R | |
| D2 | Catégorie | Champ `categorie` visible et enregistrable (gouvernance, technique, …) | ☐ C ☐ NC ☐ R | |
| D3 | Lien évaluation | `evaluation_periodique_id` nullable — rattachement depuis fiche recommandation | ☐ C ☐ NC ☐ R | |
| D4 | Score recommandations | Composante score = f(taux exécution moyen, taux réalisées) | ☐ C ☐ NC ☐ R | |
| D5 | Stats hub | Carte recommandations : total, réalisées, taux moyen cohérents | ☐ C ☐ NC ☐ R | |

### Bloc E — Missions de suivi (P2)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| E1 | Liste missions | GET `/suivi-evaluation/missions?exercice=` — pagination OK | ☐ C ☐ NC ☐ R | |
| E2 | Création mission | POST mission — numéro auto `MS-2026-NNN` | ☐ C ☐ NC ☐ R | |
| E3 | Types mission | Types suivi / évaluation / audit / contrôle disponibles | ☐ C ☐ NC ☐ R | |
| E4 | Fiche mission | Détail mission — objectif, statut, score, plans liés | ☐ C ☐ NC ☐ R | |
| E5 | Lien évaluation | Mission rattachée à `evaluation_periodique_id` (démo MS-2026-001) | ☐ C ☐ NC ☐ R | |
| E6 | Stats hub | Carte « Missions S&E » — en cours / clôturées | ☐ C ☐ NC ☐ R | |

### Bloc F — Plans d'amélioration et actions (P2)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| F1 | Liste plans | GET `/suivi-evaluation/plans?exercice=` | ☐ C ☐ NC ☐ R | |
| F2 | Création plan | POST plan — numéro auto `PA-2026-NNN` | ☐ C ☐ NC ☐ R | |
| F3 | Ajout action | POST `/suivi-evaluation/plans/{id}/actions` — action créée | ☐ C ☐ NC ☐ R | |
| F4 | Recalcul taux | Taux plan = moyenne des taux actions (ex. 2 actions → 60 %) | ☐ C ☐ NC ☐ R | |
| F5 | Lien recommandation | Action avec `recommandation_id` optionnel | ☐ C ☐ NC ☐ R | |
| F6 | Chaîne complète | Évaluation → Mission → Plan → Actions traçable (démo seeder) | ☐ C ☐ NC ☐ R | |
| F7 | Stats hub | Carte plans — en cours, taux moyen | ☐ C ☐ NC ☐ R | |

### Bloc G — Rapports et exports (P1)

| ID | Rapport / export | Formats | Résultat | Observations |
|----|------------------|---------|----------|--------------|
| G1 | Rapport `suivi_evaluation` | PDF | ☐ C ☐ NC ☐ R | |
| G2 | Rapport `suivi_evaluation` | Excel | ☐ C ☐ NC ☐ R | |
| G3 | Rapport `suivi_evaluation` | Word | ☐ C ☐ NC ☐ R | |
| G4 | KPI rapport = hub | Score global rapport = score hub (écart ≤ 0,1 pt) | ☐ C ☐ NC ☐ R | |
| G5 | Historique évaluations | Table historique présente dans le rapport | ☐ C ☐ NC ☐ R | |
| G6 | Chaîne S&E rapport | Section missions / plans dans le rapport | ☐ C ☐ NC ☐ R | |

### Bloc H — Sécurité, permissions et gouvernance

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| H1 | Permission view | `suivi_evaluation.view` ou alias `suivi.voir` → accès hub | ☐ C ☐ NC ☐ R | |
| H2 | Permission validate | `suivi_evaluation.validate` ou `suivi.valider` → génération évaluation | ☐ C ☐ NC ☐ R | |
| H3 | Permission create | `suivi_evaluation.create` → création mission / plan | ☐ C ☐ NC ☐ R | |
| H4 | Rôle commissaire | Commissaire : view + validate + export (seeder) | ☐ C ☐ NC ☐ R | |
| H5 | Rôle chef_service | Chef service : view + create + recommend | ☐ C ☐ NC ☐ R | |
| H6 | Traçabilité | Création mission / plan journalisée (`Auditable`) | ☐ C ☐ NC ☐ R | |
| H7 | Production seeder | `ProductionPermissionsSeeder` inclut `suivi_evaluation.*` | ☐ C ☐ NC ☐ R | |

### Bloc I — API REST (P3)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| I1 | Jeton scope `suivi` | POST `/api/v1/token` → 201 | ☐ C ☐ NC ☐ R | |
| I2 | Synthèse API | GET `/api/v1/suivi-evaluation/synthese?exercice=` — commissaire | ☐ C ☐ NC ☐ R | |
| I3 | Évaluations API | GET `/api/v1/suivi-evaluation/evaluations?exercice=` | ☐ C ☐ NC ☐ R | |
| I4 | Missions API | GET `/api/v1/suivi-evaluation/missions?exercice=` | ☐ C ☐ NC ☐ R | |
| I5 | Plans API | GET `/api/v1/suivi-evaluation/plans?exercice=` | ☐ C ☐ NC ☐ R | |
| I6 | Refus sans permission | Jeton valide, utilisateur sans droit → 403 | ☐ C ☐ NC ☐ R | |
| I7 | Refus scope tompro | Jeton `tompro` seul → 403 | ☐ C ☐ NC ☐ R | |
| I8 | Journalisation API | Appel enregistré dans `api_logs` | ☐ C ☐ NC ☐ R | |

### Bloc J — Intégrations transverses

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| J1 | Lien IOV | Bouton **Indicateurs IOV** → hub `/rbm/suivi-indicateurs` | ☐ C ☐ NC ☐ R | |
| J2 | Composante IOV | Score IOV cohérent avec feux globaux IOV | ☐ C ☐ NC ☐ R | |
| J3 | Formations | Composante formations reflète stats `formations` exercice | ☐ C ☐ NC ☐ R | |
| J4 | Menu navigation | « Campagnes de suivi » distinct de « Suivi-évaluation » | ☐ C ☐ NC ☐ R | |
| J5 | Données démo | `SuiviEvaluationDemoSeeder` : MS-2026-001, PA-2026-001 présents | ☐ C ☐ NC ☐ R | |

---

## 6. Synthèse quantitative

| Indicateur | Valeur |
|------------|--------|
| Nombre total de scénarios | 54 |
| Conformes (C) | |
| Non conformes (NC) | |
| Conformes avec réserve (R) | |
| Non applicables (NA) | |
| **Taux de réussite** | _____ % |

**Seuil MOA requis pour recette sans réserve :** ≥ 95 % de scénarios **C**, aucun **NC** bloquant (Blocs A, B, F).

---

## 7. Anomalies et non-conformités

| N° | ID scénario | Description | Gravité ☐ Bloquante ☐ Majeure ☐ Mineure | Responsable correction | Échéance |
|----|-------------|-------------|----------------------------------------|------------------------|----------|
| 1 | | | | | |
| 2 | | | | | |
| 3 | | | | | |

---

## 8. Réserves acceptées par la MOA

| N° | Description de la réserve | Impact | Levée avant le | Acceptée ☐ Oui ☐ Non |
|----|---------------------------|--------|----------------|----------------------|
| 1 | | | | |
| 2 | | | | |

---

## 9. Décision de la MOA

La commission de recette, après examen des résultats et des échanges avec la MOE, décide :

☐ **RECETTE ACCEPTÉE SANS RÉSERVE**  
Le module Suivi-Évaluation est déclaré conforme aux exigences de la MOA et peut être mis en service en production.

☐ **RECETTE ACCEPTÉE AVEC RÉSERVES**  
Le module est utilisable sous réserve du traitement des points listés au §8, dans les délais indiqués.

☐ **RECETTE REFUSÉE**  
Le module n'est pas recevable en l'état. Un plan de correction doit être soumis avant nouvelle recette.

**Date limite de levée des réserves (le cas échéant) :** [JJ/MM/AAAA]

**Date de la prochaine recette (le cas échéant) :** [JJ/MM/AAAA]

---

## 10. Engagements post-recette

| Partie | Engagement |
|--------|------------|
| **MOE / DSI** | Correction des NC ; support N1/N2 ; `permissions:sync-production` à chaque déploiement |
| **MOA — Suivi-évaluation** | Pilotage trimestriel des scores ; validation des évaluations périodiques |
| **Directions techniques** | Saisie des missions et plans ; mise à jour des actions correctives |
| **Contrôle financier** | Appui sur composante conformité et cohérence transversale |

---

## 11. Signatures

Fait à **Libreville**, le **09/07/2026**, en **4** exemplaires originaux.

| | |
|---|---|
| **La Responsable Suivi-évaluation / PAP** | **Le Représentant MOE / DSI** |
| *(Maître d'ouvrage — signataire principal)* | |
| Nom : Sylvie NGUEMA ADZOVÉ | Nom : Jean-Claude NKOGHE NDONG |
| Fonction : Resp. PAP / Suivi-évaluation — CEEAC | Fonction : Chef de projet SI — DSI |
| Date : | Date : |
| Signature : | Signature : |

| | |
|---|---|
| **Le Contrôleur financier** | **Le Secrétaire général** |
| Nom : Paul-André MBENG MBA | Nom : Daniel ESSAME ESONO |
| Date : | Date : |
| Signature : | Signature : |

---

## Annexe A — Grille de contrôle manuel des scores S&E

**Exercice :** 2026 — **Date :** 09/07/2026

| Composante | Poids | Score système | Contrôle métier | Écart |
|------------|-------|---------------|-----------------|-------|
| Physique PAP | 30 % | | | |
| Indicateurs IOV | 25 % | | | |
| Recommandations | 20 % | | | |
| Formations | 10 % | | | |
| Conformité suivi | 15 % | | | |
| **Score global pondéré** | 100 % | | | |
| **Niveau** | — | satisfaisant / partiel / insuffisant | | |

**Responsable Suivi-évaluation (visa annexe) :** Sylvie NGUEMA ADZOVÉ _________________ Date : 09/07/2026

---

## Annexe B — Liste des pièces jointes

| N° | Pièce | Fichier |
|----|-------|---------|
| 1 | Capture hub `/suivi-evaluation` | |
| 2 | Capture détail évaluation périodique | |
| 3 | Capture fiche plan PA-2026-001 + actions | |
| 4 | Rapport PDF `suivi_evaluation` | |
| 5 | Export Excel rapport S&E | |
| 6 | Log tests `SuiviEvaluationP0Test` … `P3Test` | |
| 7 | Réponse JSON API synthese | |
| 8 | Plan de correction NC (si applicable) | |

---

## Annexe C — Formulation type pour page de garde Word

```
COMMISSION DE LA COMMUNAUTÉ ÉCONOMIQUE DES ÉTATS DE L'AFRIQUE CENTRALE (CEEAC)

PROCÈS-VERBAL DE RECETTE FONCTIONNELLE
Module Suivi-Évaluation
Application BUDGET-PAP-SUIVI-CEEAC

Référence : PV-REC-SE-2026-01
Version applicative : [X.Y.Z]
Date : 09/07/2026

Document confidentiel — Usage interne CEEAC
```

---

## Annexe D — Checklist MEP production

| Étape | Commande / action | Fait ☐ | Date | Responsable |
|-------|-------------------|--------|------|-------------|
| 1 | Sauvegarde base de données | | | DSI |
| 2 | `php artisan migrate` | | | DSI |
| 3 | `php artisan permissions:sync-production --force` | | | DSI |
| 4 | Vérification hub `/suivi-evaluation` (commissaire / chef_service) | | | MOA |
| 5 | Test `suivi-evaluation:capture --exercice=2026` | | | DSI |
| 6 | Test API jeton `suivi` — endpoints S&E | | | DSI |
| 7 | Planification cron capture trimestrielle (optionnel) | | | DSI |
| 8 | Formation points focaux S&E (1 h) | | | MOA |
| 9 | Archivage du présent PV signé (GED) | | | MOA |

---

### Conseils de mise en forme Word

1. Styles : Titre 1 / Titre 2 / Normal ; tableaux avec en-tête gris `#0a4a8f`.
2. Numérotation automatique des sections (1, 1.1, …).
3. Cases à cocher : contrôle de contenu Word ou symboles ☐ / ☑.
4. Page de garde : logo CEEAC + référence PV-REC-SE-2026-01.
