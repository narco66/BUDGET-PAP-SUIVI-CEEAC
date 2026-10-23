# Procès-verbal de recette MOA
## Module Suivi Financier & Suivi des Indicateurs — BUDGET-PAP-SUIVI-CEEAC

**Document type :** Procès-verbal de recette fonctionnelle  
**Version du modèle :** 1.0 — Juin 2026  
**Application :** BUDGET-PAP-SUIVI-CEEAC  
**Modules recettés :** Suivi Financier (P0 → P3) · Suivi des Indicateurs IOV (P0 → P3)

---

> **Mode d'emploi :** copiez ce contenu dans Microsoft Word, appliquez la charte graphique CEEAC (en-tête, pied de page, numérotation), exportez en PDF pour signature. Les champs entre `[crochets]` sont à compléter.

---

| **Référence PV** | PV-REC-SF-2026-01 |
|------------------|------------------------|
| **Date de recette** | 25/06/2026 *(mercredi — session planifiée)* |
| **Lieu** | Siège CEEAC — Libreville — Bâtiment Administratif — Salle de conférence du Secrétariat Général (2e étage, aile Est) |
| **Exercice testé** | 2026 |
| **Version applicative** | [ex. v2.4.0 / commit …] |
| **Environnement** | ☑ Préproduction   ☐ Production   ☐ Autre : _________ |

---

## 1. Objet du procès-verbal

Le présent procès-verbal atteste de la recette fonctionnelle du **module Suivi Financier** et, le cas échéant, du **module Suivi des Indicateurs IOV** de l'application **BUDGET-PAP-SUIVI-CEEAC**, réalisée sous la responsabilité de la Maîtrise d'Ouvrage (MOA), représentée par le **Contrôleur financier** et le **Responsable Suivi-évaluation / PAP** de la Commission de la CEEAC.

Il constate, après exécution des scénarios de test définis ci-après, que le dispositif permet :

- le suivi permanent des crédits ouverts, engagés, liquidés, ordonnancés, payés et disponibles ;
- le calcul des **six taux** d'exécution et des taux intermédiaires de la chaîne de la dépense ;
- le suivi des **restes à payer** et **restes à engager** ;
- la capture et l'historisation des **situations budgétaires** (`situations_budgetaires`) ;
- le rapprochement **physique / financier** avec le PAP et la détection des écarts ;
- le pilotage des **indicateurs IOV** (feux, tendances, snapshots `performances_indicateurs`) ;
- la production des rapports, exports et accès **API REST** requis ;
- le respect des règles de sécurité, traçabilité et contrôle interne.

---

## 2. Contexte et périmètre

### 2.1 Contexte

La Commission de la CEEAC a engagé le renforcement du dispositif de pilotage budgétaire et financier via l'application intégrée **BUDGET-PAP-SUIVI-CEEAC**. Un audit de conformité a identifié un niveau de maturité initial et un plan de correction en quatre phases (P0 à P3), désormais implémenté.

### 2.2 Périmètre recetté

| Inclus | Exclus |
|--------|--------|
| Calcul unifié des indicateurs financiers — `SuiviFinancierCalculator` (P0) | Module comptabilité générale SYSCOHADA |
| Restes à payer / à engager, snapshots `situations_budgetaires` (P1) | Interface bancaire temps réel |
| Hub Suivi Financier, permissions `suivi_financier.*`, écarts PAP (P2) | Migration de données historiques antérieures à [AAAA] |
| Hub Suivi Indicateurs, snapshots `performances_indicateurs` (P1–P2) | Personnalisation hors spec validée |
| Seeder production, API REST, documentation (P3) | |

### 2.3 Références

| Réf. | Document |
|------|----------|
| [AUD-SF-2026] | Rapport d'audit module Suivi Financier |
| [AUD-IOV-2026] | Rapport d'audit module Suivi des Indicateurs |
| [DOC-SF] | `docs/module-suivi-financier.md` |
| [DOC-IOV] | `docs/module-suivi-indicateurs.md` |
| [DOC-API] | `docs/api-suivi-financier-indicateurs.md` |
| [CONV-REC] | `docs/convocation-recette-moa-suivi-financier.md` |
| [CONV-DEMO] | `docs/convocation-demo-DEMO-SF-2026-01.md` — démo 19/06/2026, Lab SI DSI |
| [ANNUAIRE] | `docs/annuaire-recette-suivi-financier-2026.md` |
| [PROC-CLOTURE] | Procédure de clôture budgétaire CEEAC |

---

## 3. Composition de la commission de recette

| Rôle | Nom et prénom | Fonction | Structure | Signature |
|------|---------------|----------|-----------|-----------|
| **Président de séance — MOA** | Paul-André **MBENG MBA** | Contrôleur financier | Direction du Contrôle financier | |
| Membre | Grace **ONDO** | Gestionnaire budgétaire | Direction du Budget — Svc exécution | |
| Membre | Daniel **ESSAME ESONO** | Secrétaire général | Secrétariat Général | |
| Membre | Sylvie **NGUEMA ADZOVÉ** | Resp. PAP / Suivi-évaluation | Direction Planification & Suivi-éval. | |
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
| Jeu de données | ☑ Données réelles anonymisées   ☐ `SafeDatabaseSeeder`   ☐ Mixte |
| Comptes utilisateurs testés | p.mbeng / g.ondo / s.nguema / j.nkoghe / administrateur |
| Date du dernier déploiement | [JJ/MM/AAAA] |
| Contact secrétariat recette | Chantal MBOUMBA — c.mboumba@ceeac.int — +241 01 44 47 22 |
| Migrations exécutées | `php artisan migrate` → ☐ OK   ☐ KO |
| Permissions synchronisées | `php artisan permissions:sync-production --force` → ☐ OK   ☐ KO |
| Tests automatisés | Voir § 4.1 |

### 4.1 Commandes de vérification pré-recette (MOE)

```bash
php artisan migrate
php artisan permissions:sync-production --force
php artisan test --filter=SuiviFinancierP0Test,SuiviFinancierP1Test,SuiviFinancierP2Test,SuiviIndicateursP0Test,SuiviIndicateursP1Test,SuiviIndicateursP2Test,SuiviP3Test
php artisan budget:audit-compteurs --exercice=[AAAA]
```

| Commande | Résultat attendu | Constat |
|----------|------------------|---------|
| Suite de tests ci-dessus | 100 % vert | ☐ OK   ☐ KO |
| `budget:audit-compteurs` | 0 anomalie sur jeu propre | ☐ OK   ☐ KO |

---

## 5. Grille de recette fonctionnelle

**Légende résultat :**  
☐ **C** — Conforme   ☐ **NC** — Non conforme   ☐ **NA** — Non applicable   ☐ **R** — Conforme avec réserve

### Bloc A — Consommation et crédits (P0 / P1)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| A1 | Affichage crédits ouverts | Dashboard et hub `/suivi-financier` affichent le total `montant_ouvert` cohérent avec les lignes budgétaires | ☐ C ☐ NC ☐ R | |
| A2 | Budget révisé | Après révision à la baisse, le **budget adopté** = `COALESCE(montant_revise, montant_ouvert)` ; taux recalculés | ☐ C ☐ NC ☐ R | |
| A3 | Crédits engagés | Montant engagé cohérent avec la chaîne de la dépense | ☐ C ☐ NC ☐ R | |
| A4 | Crédits liquidés / ordonnancés / payés | Compteurs ligne mis à jour après validation workflow | ☐ C ☐ NC ☐ R | |
| A5 | Crédits disponibles | Disponible = budget adopté − engagé | ☐ C ☐ NC ☐ R | |
| A6 | Cohérence inter-écrans | Dashboard = Hub Suivi Financier = Reporting (écart ≤ 0,01 XAF) | ☐ C ☐ NC ☐ R | |

### Bloc B — Taux d'exécution (P0)

| ID | Indicateur | Formule validée | Résultat | Observations |
|----|------------|-----------------|----------|--------------|
| B1 | Taux d'engagement | Engagé / Budget adopté × 100 | ☐ C ☐ NC ☐ R | |
| B2 | Taux de liquidation | Liquidé / Engagé × 100 | ☐ C ☐ NC ☐ R | |
| B3 | Taux d'ordonnancement | Ordonnancé / Liquidé × 100 | ☐ C ☐ NC ☐ R | |
| B4 | Taux de paiement | Payé / Ordonnancé × 100 | ☐ C ☐ NC ☐ R | |
| B5 | Taux d'exécution financière | Payé / Budget adopté × 100 | ☐ C ☐ NC ☐ R | |
| B6 | Cas dénominateur nul | Taux = 0 % sans erreur système | ☐ C ☐ NC ☐ R | |

### Bloc C — Restes et snapshots (P1)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| C1 | Restes à engager | = Budget adopté − Engagé ; visible dashboard et hub | ☐ C ☐ NC ☐ R | |
| C2 | Restes à payer | = Ordonnancé − Payé ; cohérent avec export Excel | ☐ C ☐ NC ☐ R | |
| C3 | Export restes à payer | Route `depense.export.restes-payer` — colonnes complètes | ☐ C ☐ NC ☐ R | |
| C4 | Export restes à engager | Route `depense.export.restes-engager` — par ligne budgétaire | ☐ C ☐ NC ☐ R | |
| C5 | Snapshot situation | `php artisan budget:situation-capture` → ligne dans `situations_budgetaires` ; relecture = calcul live | ☐ C ☐ NC ☐ R | |
| C6 | Capture à la clôture | Clôture exercice → snapshot automatique (`ExerciceService::cloturer`) | ☐ C ☐ NC ☐ R | |

### Bloc D — Chaîne de la dépense

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| D1 | Engagement sans crédit | Refus si disponible insuffisant | ☐ C ☐ NC ☐ R | |
| D2 | Liquidation sans engagement visé | Impossible | ☐ C ☐ NC ☐ R | |
| D3 | Ordonnancement sans liquidation validée | Refus (HTTP 422 ou équivalent) | ☐ C ☐ NC ☐ R | |
| D4 | Paiement sans ordonnancement | Impossible | ☐ C ☐ NC ☐ R | |
| D5 | Invariants montants | Engagé ≤ adopté ; Liquidé ≤ Engagé ; Ordonnancé ≤ Liquidé ; Payé ≤ Ordonnancé | ☐ C ☐ NC ☐ R | |
| D6 | Audit compteurs | `budget:audit-compteurs` → 0 anomalie sur jeu propre | ☐ C ☐ NC ☐ R | |
| D7 | Anomalies hub | Écran `/suivi-financier/anomalies` cohérent avec audit compteurs | ☐ C ☐ NC ☐ R | |

### Bloc E — Hub Suivi Financier et intégration PAP (P2)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| E1 | Accès hub | Menu **Suivi Financier** visible pour `controleur_financier` | ☐ C ☐ NC ☐ R | |
| E2 | Refus sans permission | Compte `point_focal` → HTTP 403 sur `/suivi-financier` | ☐ C ☐ NC ☐ R | |
| E3 | Onglets hub | Chaîne, structure, écarts PAP, ventilation tâche affichés | ☐ C ☐ NC ☐ R | |
| E4 | Lien activité ↔ ligne budgétaire | Taux financier activité cohérent avec montant payé ligne | ☐ C ☐ NC ☐ R | |
| E5 | Écart physique / financier | Rapport `ecarts_physique_financier` identifie sur/sous-financiation | ☐ C ☐ NC ☐ R | |
| E6 | Capture manuelle situation | POST `/suivi-financier/situation-capture` (permission `suivi_financier.validate`) | ☐ C ☐ NC ☐ R | |

### Bloc F — Rapports et exports

| ID | Rapport / export | Formats | Résultat | Observations |
|----|------------------|---------|----------|--------------|
| F1 | Synthèse exécution budgétaire | PDF / Excel | ☐ C ☐ NC ☐ R | |
| F2 | Situation budgétaire (`situation_budgetaire`) | PDF / Excel | ☐ C ☐ NC ☐ R | |
| F3 | Restes à payer | Excel / CSV | ☐ C ☐ NC ☐ R | |
| F4 | Restes à engager | Excel / CSV | ☐ C ☐ NC ☐ R | |
| F5 | Écarts physique / financier (`ecarts_physique_financier`) | PDF / Excel | ☐ C ☐ NC ☐ R | |
| F6 | Identité inter-formats | Montants identiques PDF = Excel pour même exercice | ☐ C ☐ NC ☐ R | |

### Bloc G — Sécurité et gouvernance

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| G1 | Permissions hub | Accès refusé sans `suivi_financier.view` (ni `tableau_bord.voir` / `rapport.voir`) | ☐ C ☐ NC ☐ R | |
| G2 | Export sécurisé | Export refusé sans `suivi_financier.export` (ni permissions équivalentes) | ☐ C ☐ NC ☐ R | |
| G3 | Séparation des fonctions | Ordonnateur ≠ liquidateur sur même dossier testé | ☐ C ☐ NC ☐ R | |
| G4 | Exercice clos | Modification budget/dépense bloquée | ☐ C ☐ NC ☐ R | |
| G5 | Traçabilité | Modification tracée dans journal d'audit | ☐ C ☐ NC ☐ R | |
| G6 | Permissions production | `ProductionPermissionsSeeder` — rôles `suivi_financier.*` attribués | ☐ C ☐ NC ☐ R | |

### Bloc H — API REST et industrialisation (P3)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| H1 | Jeton API scope `suivi` | POST `/api/v1/token` avec `abilities: ["suivi"]` → 201 | ☐ C ☐ NC ☐ R | |
| H2 | Synthèse API | GET `/api/v1/suivi-financier/synthese?exercice=` — contrôleur financier | ☐ C ☐ NC ☐ R | |
| H3 | Écarts API | GET `/api/v1/suivi-financier/ecarts?exercice=` | ☐ C ☐ NC ☐ R | |
| H4 | Situations API | GET `/api/v1/suivi-financier/situations?exercice=` | ☐ C ☐ NC ☐ R | |
| H5 | Refus scope tompro | Jeton `tompro` seul → 403 sur endpoints suivi | ☐ C ☐ NC ☐ R | |
| H6 | Journalisation API | Appel enregistré dans `api_logs` | ☐ C ☐ NC ☐ R | |
| H7 | Tests automatisés P3 | `SuiviP3Test` : 100 % vert | ☐ C ☐ NC ☐ R | |

### Bloc I — Suivi des Indicateurs IOV (P0 → P3)

| ID | Scénario | Critères d'acceptation | Résultat | Observations |
|----|----------|------------------------|----------|--------------|
| I1 | Hub indicateurs | GET `/rbm/suivi-indicateurs` — feux, évolution, alertes | ☐ C ☐ NC ☐ R | |
| I2 | Historique mesure | Modification mesure validée tracée dans `historique_mesures` | ☐ C ☐ NC ☐ R | |
| I3 | Blocage mesure validée | Impossible de modifier une mesure au statut `validee` | ☐ C ☐ NC ☐ R | |
| I4 | Snapshot à validation | Validation mesure → ligne `performances_indicateurs` | ☐ C ☐ NC ☐ R | |
| I5 | Commande batch | `php artisan indicateurs:capture-performances --exercice=[AAAA]` | ☐ C ☐ NC ☐ R | |
| I6 | Rapport évolution | Rapport `indicateurs_evolution` — PDF / Excel | ☐ C ☐ NC ☐ R | |
| I7 | API performances | GET `/api/v1/suivi-indicateurs/performances?exercice=` | ☐ C ☐ NC ☐ R | |

---

## 6. Synthèse quantitative

| Indicateur | Valeur |
|------------|--------|
| Nombre total de scénarios | 52 |
| Conformes (C) | |
| Non conformes (NC) | |
| Conformes avec réserve (R) | |
| Non applicables (NA) | |
| **Taux de réussite** | _____ % |

**Seuil MOA requis pour recette sans réserve :** ≥ 95 % de scénarios **C**, aucun **NC** bloquant (Blocs A, B, D).

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
Le module Suivi Financier (et le module Suivi des Indicateurs, le cas échéant) est déclaré conforme aux exigences de la MOA et peut être mis en service en production.

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
| **MOE / DSI** | Correction des NC dans les délais ; support N1/N2 ; sauvegarde avant clôture exercice ; exécution de `permissions:sync-production` à chaque déploiement |
| **MOA** | Validation des snapshots mensuels ; signalement des écarts ; respect des workflows |
| **Contrôleur financier** | Pilotage du dispositif ; validation des situations budgétaires de clôture |
| **Responsable Suivi-évaluation** | Validation des snapshots IOV ; suivi des indicateurs rouges |

---

## 11. Signatures

Fait à **Libreville**, le **25/06/2026**, en **4** exemplaires originaux.

| | |
|---|---|
| **Le Contrôleur financier** | **Le Représentant MOE / DSI** |
| *(Maître d'ouvrage — signataire principal)* | |
| Nom : Paul-André MBENG MBA | Nom : Jean-Claude NKOGHE NDONG |
| Fonction : Contrôleur financier — CEEAC | Fonction : Chef de projet SI — DSI |
| Date : | Date : |
| Signature : | Signature : |

| | |
|---|---|
| **Le Secrétaire général** | **Le Responsable Suivi-évaluation / PAP** |
| *(Validation ordonnancement / pilotage)* | *(Validation IOV)* |
| Nom : Daniel ESSAME ESONO | Nom : Sylvie NGUEMA ADZOVÉ |
| Date : | Date : |
| Signature : | Signature : |

---

## Annexe A — Feuille de calcul des taux (contrôle manuel)

**Exercice :** 2026 — **Date :** 25/06/2026

| Indicateur | Montant (XAF) | Calcul | Taux attendu | Taux système | Écart |
|------------|---------------|--------|--------------|--------------|-------|
| Budget initial (ouvert) | | | — | | |
| Budget révisé / adopté | | | — | | |
| Engagé | | | Engagement = / adopté | | |
| Liquidé | | | Liquidation = / engagé | | |
| Ordonnancé | | | Ordonnancement = / liquidé | | |
| Payé | | | Paiement = / ordonnancé | | |
| | | | **Exécution** = payé / adopté | | |
| Reste à engager | | adopté − engagé | | | |
| Reste à payer | | ordonnancé − payé | | | |

**Contrôleur financier (visa annexe) :** Paul-André MBENG MBA _________________ Date : 25/06/2026

---

## Annexe B — Liste des pièces jointes

| N° | Pièce | Fichier |
|----|-------|---------|
| 1 | Captures écran dashboard / hub Suivi Financier | |
| 2 | Captures écran hub Suivi Indicateurs | |
| 3 | Export Excel restes à payer | |
| 4 | Export Excel restes à engager | |
| 5 | Rapport PDF situation budgétaire | |
| 6 | Rapport PDF écarts physique/financier | |
| 7 | Log tests automatisés (`php artisan test --filter=Suivi…`) | |
| 8 | Log `budget:audit-compteurs` | |
| 9 | Plan de correction NC (si applicable) | |

---

## Annexe C — Formulation type pour page de garde Word

```
COMMISSION DE LA COMMUNAUTÉ ÉCONOMIQUE DES ÉTATS DE L'AFRIQUE CENTRALE (CEEAC)

PROCÈS-VERBAL DE RECETTE FONCTIONNELLE
Modules Suivi Financier & Suivi des Indicateurs
Application BUDGET-PAP-SUIVI-CEEAC

Référence : PV-REC-SF-[AAAA]-[NN]
Version applicative : [X.Y.Z]
Date : [JJ/MM/AAAA]

Document confidentiel — Usage interne CEEAC
```

---

## Annexe D — Checklist MEP production

| Étape | Commande / action | Fait ☐ | Date | Responsable |
|-------|-------------------|--------|------|-------------|
| 1 | Sauvegarde base de données | | | DSI |
| 2 | `php artisan migrate` | | | DSI |
| 3 | `php artisan permissions:sync-production --force` | | | DSI |
| 4 | Vérification hub `/suivi-financier` (controleur_financier) | | | MOA |
| 5 | Vérification hub `/rbm/suivi-indicateurs` | | | MOA |
| 6 | Test API jeton `suivi` | | | DSI |
| 7 | Planification cron `budget:situation-capture` (optionnel) | | | DSI |
| 8 | Planification cron `indicateurs:capture-performances` (optionnel) | | | DSI |
| 9 | Archivage du présent PV signé (GED) | | | MOA |

---

### Conseils de mise en forme Word

1. Styles : Titre 1 / Titre 2 / Normal ; tableaux avec en-tête gris `#0a4a8f`.
2. Numérotation automatique des sections (1, 1.1, …).
3. Cases à cocher : contrôle de contenu Word ou symboles ☐ / ☑.
4. Pied de page : « CEEAC — PV Recette Suivi Financier — [Réf] — Page X/Y ».
5. Export PDF/A pour archivage GED.
