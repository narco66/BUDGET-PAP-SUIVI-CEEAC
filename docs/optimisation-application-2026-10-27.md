# Optimisation de l'application et des vues — 27/10/2026

## 1. Méthode

Les corrections ne reposent pas sur des impressions mais sur une mesure automatique. Chaque page de l'application est ouverte comme le ferait un utilisateur :

- 215 pages sans paramètre ;
- 57 fiches de détail (ouvertes sur l'enregistrement le plus récent) ;
- deux profils : administrateur et chef de service.

Pour chaque page, on relève le code de réponse, le nombre de requêtes SQL, les requêtes répétées, le temps de rendu et le poids du HTML. Toutes les mesures s'exécutent dans une transaction annulée : aucune écriture en base.

**État initial.** Aucune erreur 500. Le gabarit commun est léger (2 requêtes au minimum). Les défauts sont concentrés sur quelques pages : boucles de requêtes, appels réseau inutiles, pages HTML très lourdes, aucune compression, aucun cache navigateur.

## 2. Corrections et gains mesurés

### 2.1 Requêtes répétées (N+1)

| Page | Cause | Correction | Requêtes | Temps |
|---|---|---|---|---|
| Cockpit de gouvernance | 3 requêtes par activité pour compter les activités protégées (`RbmAuditService`) | Un seul comptage SQL, mêmes critères (résultat identique vérifié : 14) | 1 059 → 179 | 805 → 306 ms |
| Tableau de bord structure du budget | Une somme des lignes par activité (`StructureBudgetCoherenceService::ecartsMontantPap`) | Sous-requête groupée jointe | 380 → 32 | 396 → 139 ms |
| Contrôle interne | 25 comptages (5 composantes × 5 évaluations) | Une requête groupée | 29 → 5 | 105 → 77 ms |
| Recherche structure du budget | Ligne rechargée pour chaque contrôle de droit | Lignes de la page chargées en une fois | 37 → 13 | 212 → 175 ms |
| Référentiel financier, rapport budgétaire | Ligne et somme des sources lues par activité (`BudgetAuditService`) | Chargement anticipé et `withSum` | 86 → 67 | 275 → 190 ms |
| Tableau de bord workflow | Objet de chaque dernière action lu un par un | Une requête par type d'objet | 37 → 25 | 176 → 150 ms |
| Formulaire EB (création, modification) | Une ligne d'imputation et une enveloppe par activité (`ImputationPapService`) | Lignes et enveloppes « ACT-* » chargées en une requête | 54 → 35 | — |
| Préparation budgétaire | Budget relu pour chaque préparation (50 fois) | Relation renseignée une fois | 68 → 17 | 176 → 147 ms |
| Listes, Mes Tâches, validations | Structure validatrice recalculée pour chaque dossier (`WorkflowInstitutionResolver`) | Mémorisée pendant la requête, invalidée à chaque modification de l'organigramme | Besoins : 51 → 43 | — |
| Registre GED et pages dérivées | 2 requêtes de partage et un recalcul du périmètre par ligne | Partages et périmètre préchargés pour la liste (règles d'accès inchangées) | 40 → 28 au rendu | — |

### 2.2 Temps de réponse

| Page | Cause | Correction | Avant | Après |
|---|---|---|---|---|
| Administration technique | Tentative de connexion à Redis jusqu'au délai d'expiration, alors que Redis n'est pas utilisé (cache et file sur la base) | Pas de connexion si Redis n'est pas utilisé ; statut « non utilisé » | 2 381 ms | 100 ms |

### 2.3 Poids des vues et transfert réseau

**Compression gzip par l'application** (middleware `CompresserReponse`) : pages HTML et réponses JSON, quand le navigateur l'accepte.

| Page | Sans compression | Avec compression |
|---|---|---|
| Arbre RBM | 1 284 Ko | 57 Ko |
| Fiche PAP | 726 Ko | 49 Ko |
| Matrice des permissions | 591 Ko | 14 Ko |
| Pilotage PAP | 536 Ko | 36 Ko |
| Expressions du besoin | 142 Ko | 12 Ko |

**Arbre RBM.** Les trois formulaires par nœud (archiver, supprimer, restaurer) sont remplacés par des boutons et un formulaire unique partagé. Confirmation et action restent identiques. Gain : 1 580 → 1 284 Ko avant compression.

**Bootstrap et Bootstrap Icons servis en local** (`public/vendor`, versions identiques 5.3.3 et 1.11.3). L'affichage ne dépend plus de jsdelivr, donc plus de la connexion internet du réseau CEEAC.

**Cache navigateur d'un an** pour les ressources versionnées (`build/assets`, `vendor/bootstrap-*`), via `public/.htaccess` et `mod_headers`.

### 2.4 Base de données

Une migration ajoute 11 index sur des clés étrangères et des colonnes filtrées qui n'en avaient pas. Elle ne fait qu'ajouter, est idempotente et ne touche à aucune donnée :

- `audit_workflow` (objet, instance, circuit et date) ;
- `workflow_instance_etapes(instance_id, ordre)` ;
- `liquidations(engagement_id)`, `ordonnancements(liquidation_id)`, `paiements(ordonnancement_id)` ;
- `engagement_imputations(engagement_id)` ;
- `ged_partages(document_id)`, `ged_partages(partage_avec)` ;
- `lignes_budgetaires(activite_id)`.

### 2.5 Fiabilité

Les mémoires de requête (instances « scoped ») sont remises à zéro à la fin de chaque requête HTTP, comme sous Octane. Aucune donnée mémorisée ne survit à la requête qui l'a calculée, même quand un processus enchaîne plusieurs requêtes (tests, serveur persistant).

## 3. Contrôles

- Toutes les pages mesurées répondent sans erreur 500, avant comme après les corrections, pour les deux profils.
- Chaque correction de comptage est vérifiée par comparaison avec l'ancienne méthode : activités protégées, sommes par activité, écarts.
- Les tests ciblés passent (Mes Tâches, GED, listes, traçabilité). Le résultat de la suite complète figure dans la réponse de livraison.

## 4. Recommandations (non appliquées, décision requise)

1. **Activer `mod_deflate` (ou `mod_brotli`) et `mod_expires` sur Apache.** Ces modules sont présents mais désactivés dans Laragon ; c'est une configuration du serveur, commune à tous les sites. Ils compresseraient aussi le CSS et le JavaScript (Bootstrap : 233 Ko → environ 31 Ko).
2. **En production, activer les caches Laravel** (`php artisan optimize` : configuration, routes, vues, événements) et OPcache. À exécuter au déploiement, pas en développement.
3. **Arbre RBM** : un affichage repliable avec chargement à la demande des niveaux profonds réduirait encore le rendu (environ 580 nœuds aujourd'hui).
4. **ParaTest** (`brianium/paratest`) permettrait de lancer la suite de tests en parallèle (`php artisan test --parallel`).
