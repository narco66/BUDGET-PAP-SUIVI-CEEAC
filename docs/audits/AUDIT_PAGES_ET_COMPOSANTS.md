# Audit des pages et composants — provenance des données affichées

**Date :** 25 octobre 2026 · **Couverture :** 255 chemins distincts parcourus au navigateur (10 profils) ; 329 routes GET côté serveur en tant qu'administrateur ; analyse statique de `resources/views`, `frontend/src`, `resources/js`, `app/Http`, `app/Services`.

## 1. Données codées en dur ou fictives

| Recherche | Résultat |
|---|---|
| `faker`, `fake()`, `mock` hors tests, seeders et factories | aucune occurrence |
| `Math.random`, jeux de données de démonstration dans le front | aucune occurrence |
| Tableaux de montants en dur (≥ 4 nombres) dans les vues, composants et services | aucune occurrence (hors modèles d'import `Exports/Modele*`, qui sont des gabarits) |
| Exercice codé en dur affiché | **2 occurrences corrigées** : en-tête (« Exercice 2026 · XAF ») et groupes d'imputation du formulaire EB (« Budget 2026 »), désormais lus en base (`Exercice::courant()`, exercice du dossier) |
| Années servant de valeur de repli (`date('Y')`, `now()->year`) | numérotation des dossiers et repli en l'absence d'exercice : sans effet sur les données affichées |
| `StructureBudgetRecetteService`, `PapCompletenessService` (codes `LB-2026-*`) | outils de recette et de reprise propres au Budget 2026, non utilisés pour l'affichage courant |

## 2. Chaîne de provenance des pages clés

| Page | Contrôleur → service → source PostgreSQL | Vérification |
|---|---|---|
| `/budget` | `BudgetEnveloppeController` → `LigneCreditsService` → `lignes_budgetaires` | 50/50 lignes concordantes |
| `/besoins`, `/engagements`, `/depense/{type}` | contrôleurs de liste → modèles Eloquent → tables des dossiers | 138/138 dossiers concordants |
| `/dashboard`, `/tableaux-bord` | `TableauBordService` → Σ `lignes_budgetaires` (sans jointure aux dossiers) | totaux identiques |
| `/reporting/cockpit` | `ReportingService::cockpit` → `global`, `parAxe`, `parCategorie`, `parStructure`, `Paiement::regles()` | totaux identiques ; axes corrigés ; décaissements corrigés |
| `/depense-cockpit` | services de dépense → compteurs des lignes | totaux identiques |
| `/workflow/dashboard` | `WorkflowDashboardService` → `workflow_taches`, `workflow_instances`, `audit_workflow` | délais recalculés (activation → décision) |
| `/strategie/dashboard/decisionnel` | `StrategiePilotageService` → référentiel stratégique (vide), PAP, liaison | taux sans base remplacés par « non évaluable » |
| Exports Excel de la dépense | `DepenseExportController` → mêmes tables | 138/138 concordants |

## 3. Caches et données périmées

`CockpitCache` est versionné par une génération, incrémentée à chaque écriture de compteur : aucun chiffre périmé n'est possible. Défaut corrigé : les agrégats contenant des collections n'étaient **jamais** réutilisés, car le magasin de cache refuse toute classe à la désérialisation. Ils sont désormais stockés en types natifs.

## 4. Composants et rendu

- Graphiques ApexCharts : initialisation différée après les modules Vite (4 pages corrigées) ; les valeurs nulles sont gérées.
- Menus : droits alignés sur les contrôleurs (GED).
- Performance : arbre RBM 20,8 s → 3,0 s ; cockpit exécutif 39-42 → 21 requêtes ; tableau de pilotage stratégique 72 → 52 requêtes.
