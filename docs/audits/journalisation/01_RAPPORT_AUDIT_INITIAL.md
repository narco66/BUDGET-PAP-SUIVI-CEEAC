# 01 — Rapport d'audit initial : Journalisation, Traçabilité et Horodatage

*Date : 25/10/2026. Référentiel : `docs/Module_Journalisation_Tracabilite_Horodatage_BUDGET_CEEAC_GESBUDEP.md` v1.0 (lu intégralement). Audit réalisé en lecture seule : aucune modification du code, du schéma ni des données pendant cette phase.*

## 1. Contexte et précautions

| Élément | Constat |
|---|---|
| Dépôt | Git, branche `audit/chaine-depense-2026` ; 109 fichiers modifiés ou non suivis (travaux antérieurs non commités : PostgreSQL, chaîne de dépense, notifications, Mes Tâches) |
| Base | PostgreSQL 18.4, `gest_budget_pap`, 127.0.0.1:5433 ; l'application se connecte avec le compte **superutilisateur `postgres`** |
| Point de restauration | `stash@{0}` (point de restauration Git du 25/10/2026) ; empreintes des données protégées prises avant intervention (`empreintes_avant.json`) |
| Maquettes Figma | Aucune dans `/docs/` : conformité visuelle aux maquettes non vérifiable, conventions de l'application suivies |
| Frontend | Interface principale Blade + Bootstrap 5 ; SPA Vue 3 (`frontend/`) partielle. React + Tailwind + Font Awesome 7 (cible du § 12) absents : ne pas reconstruire |

## 2. Inventaire des mécanismes existants

| Mécanisme | Composant | Volume | Rôle |
|---|---|---|---|
| **Piste d'audit centrale** | `journaux_audit`, `App\Support\AuditLogger`, trait `App\Models\Concerns\Auditable` | 2 871 lignes | Créations, modifications (avant/après), suppressions sur **73 modèles** ; ~98 appels métier explicites (login, logout, MFA, mots de passe, rôles, utilisateurs, exercices, imports, engagements, structure budgétaire, PAP, SoD, restaurations…) ; rôle de l'acteur figé |
| Journal des connexions | `login_logs` (`LoginController`) | 58 | Succès et échecs de connexion, motif, IP |
| Journal API | `api_logs`, `api_audit_logs` | 1 135 / 2 | Requêtes API (méthode, chemin, statut, durée, empreinte de requête) |
| Journal du workflow | `audit_workflow` (`WorkflowAuditService`) | 325 | Démarrages, décisions, renvois, rejets, SoD par instance et étape |
| Visas électroniques | `visas_electroniques` | 206 | Empreinte SHA-256 du contenu visé par étape (horodatage serveur, non qualifié) |
| Événements « Mes Tâches » | `workflow_tache_evenements` (modèle immuable) | 212 | Création, prise en charge, réaffectation, suspension, décision, délégation (`pour_compte_de`) |
| Journal GED | `ged_journaux` | 4 | Dépôts, versions, téléchargements, avant/après |
| Journaux spécialisés | `audit_notifications`, `audit_reporting`, `audit_depenses`, `audit_suivi_physique`, `audit_integrite_data` | 457 / 4 / 0 / 0 / 0 | Notifications, rapports, contrôles de cohérence métier |
| Historiques métier | `budget_historiques`, `pap_historiques`, `rbm_historiques`, `historique_depenses`, `historiques_affectations`, `historique_mesures` | — | Versions métier |
| Alertes | `alertes` (`AlerteRegistryService`, escalade) | 0 | Registre transversal ; criticités information/attention/avertissement/critique/urgence |
| Rétention | `config/audit.php`, `AuditRetentionService`, commande `audit:purge-journaux` | — | Purge par table au-delà d'un délai |
| Contrôle de cohérence | `DataIntegrityCheckService`, `data:integrity-check` | — | Cohérence des **données métier** (pas des journaux) |
| Écrans | `/audit` (`AuditController`), `/logs/connexions` (`LogController`), `/workflow/audit` | — | Liste filtrable par action et entité ; journal des connexions |

Ce socle est riche et opérationnel : **il doit être consolidé, pas remplacé**. Créer une table `audit_events` parallèle à `journaux_audit` dupliquerait les mêmes événements (interdit par la mission).

## 3. Horodatage

| Point | Constat | Écart |
|---|---|---|
| Fuseau applicatif | `config/app.php` : `timezone = UTC` | Conforme |
| Fuseau de session PostgreSQL | Non défini dans `config/database.php` : la session hérite du serveur, **`Africa/Lagos` (UTC+1)** | **Incohérence** : `now()` PHP écrit en UTC, mais les valeurs par défaut SQL (`CURRENT_TIMESTAMP`) et les requêtes brutes `now()` travaillent en UTC+1 |
| Type des colonnes | `timestamp without time zone`, **précision 0** (seconde) sur tous les journaux | Ni `TIMESTAMPTZ` ni microsecondes ; deux événements de la même seconde ne sont ordonnables que par `id` |
| Instants distincts | Seul `created_at` | `occurred_at`, `recorded_at`, `effective_at` absents |
| Affichage | `created_at->format('d/m/Y H:i:s')` sans conversion | Heures **UTC affichées comme heure locale** (décalage d'une heure à Libreville) ; `Africa/Libreville` n'est utilisé nulle part |
| Synchronisation | Hôte Windows synchronisé par NTP (`time.windows.com`, dernière synchronisation 25/10/2026 06:33) | Aucun contrôle applicatif de dérive ni alerte |
| Navigateur | Aucun horodatage officiel issu du client | Conforme |

## 4. Sécurité et intégrité

| # | Constat | Preuve | Criticité |
|---|---|---|---|
| S1 | **Journal entièrement lisible par 12 rôles sur 14** : `/audit` n'exige que `audit.voir`, détenue par président, vice-président, SG, commissaire, directeur, chef de service, gestionnaire, CF, comptable, agent comptable, **lecteur**. Valeurs avant/après, IP et URL de toute l'institution sont exposées sans cloisonnement | `AuditController::index`, matrice des permissions | Critique |
| S2 | Journal des connexions lisible avec `audit.voir` (même périmètre) | `LogController::connexions` | Majeure |
| S3 | **Aucune protection en écriture** : modèle `JournalAudit` sans garde, aucun déclencheur PostgreSQL, compte applicatif superutilisateur : toute mise à jour ou suppression est possible | `information_schema.triggers` vide ; `pg_roles.rolsuper = true` | Critique |
| S4 | **Purge automatique mensuelle** (`audit:purge-journaux`, 1er du mois 04:00, activée par défaut) : suppression physique sans archivage préalable, sans empreinte et **sans trace de la purge elle-même** | `bootstrap/app.php` l. 28 ; `AuditRetentionService::purgerTable` | Critique |
| S5 | Aucune empreinte, aucun chaînage, aucun scellement, aucune vérification des journaux | — | Critique |
| S6 | Masquage limité à `password` et `remember_token` ; les jetons, secrets MFA, clés API et codes de récupération passeraient dans `avant`/`apres` d'un modèle audité | `AuditLogger::MASQUES` | Majeure |
| S7 | L'administrateur reçoit tous les droits (`Gate::before`), y compris la consultation financière étendue | `AppServiceProvider` | Majeure (règle institutionnelle, à arbitrer) |
| S8 | Les accès refusés (HTTP 403) ne sont pas journalisés | Gestionnaire d'exceptions | Majeure |

## 5. Couverture des événements par module

| Module | Couvert | Manquant |
|---|---|---|
| Authentification | Connexion (succès/échec), déconnexion, MFA, changement et réinitialisation de mot de passe | Accès refusés |
| Utilisateurs | Création, modification, statut (`Auditable` sur `User`) | **Attribution/retrait de rôles, de profils et de structures à un utilisateur** (pivots Spatie non tracés) |
| Rôles et permissions | Création, modification (avant/après des permissions), suppression de rôle | — |
| Délégations / intérims | — | **Création, modification, fin** (`Delegation`, `Interim` non audités) |
| Paramétrage | `Parametre` audité | **Seuil d'ordonnancement** (`ChaineDepenseParametre` non audité : l'ancienne valeur est perdue à chaque modification) |
| PAP, Budget, EB, ENG, LIQ, ORD, PAY, Recettes | Mutations avant/après (`Auditable`), décisions dans `audit_workflow`, événements métier explicites (insuffisance de crédits, fractionnement, dégagement…) | Événement de décision **structuré et central** (code, étape, autorité, seuil et version, titulaire représenté) ; corrélation EB → PAY |
| Mes Tâches | `workflow_tache_evenements` (complet) | Corrélation avec le journal central |
| GED | `ged_journaux` | — |
| Notifications | `audit_notifications` | — |
| Alertes | — | Historique de traitement (`Alerte` non audité) |
| Système | Sauvegardes, restaurations (journalisées) | Purge des journaux non tracée (S4) |

Corrélation : aucun `request_id`, aucun `correlation_id` ; reconstituer un parcours EB → ENG → LIQ → ORD → PAY suppose de suivre manuellement les clés étrangères.

## 6. Interfaces

L'écran `/audit` propose deux filtres (action, entité), affiche 7 colonnes, sans fiche détaillée, sans valeurs avant/après, sans période, acteur, module, référence ni export. Pas de tableau de bord, de journal de sécurité, d'historique de dossier, d'historique d'utilisateur, d'écran d'intégrité. Conventions visuelles de l'application respectées (cartes, tableaux Bootstrap, icônes Bootstrap Icons).

## 7. Fiabilité transactionnelle

Les écritures de `journaux_audit` par le trait `Auditable` s'exécutent dans la transaction de la mutation métier : si l'insertion du journal échoue, l'exception annule la décision (conforme à l'esprit de JNL-005). La journalisation de la violation SoD est volontairement faite hors transaction (persiste malgré le refus).

## 8. Risques

- Modifier `AuditLogger` touche **toutes** les écritures auditées (73 modèles) : l'enrichissement doit rester tolérant (aucun nouveau champ obligatoire) et rapide.
- Des déclencheurs PostgreSQL qui interdiraient les mises à jour pourraient casser des suppressions en cascade (clés étrangères `nullOnDelete`/`cascade`) : périmètre à limiter aux journaux sans dépendance de cascade.
- Restreindre la visibilité de `/audit` réduit l'accès actuel de 10 rôles : c'est une correction de sécurité nécessaire (S1), sans suppression de fonctionnalité.
- Le passage de la session PostgreSQL en UTC aligne les valeurs par défaut SQL sur l'application ; les lignes historiques écrites par défaut SQL en UTC+1 ne sont pas réécrites (documenté).
- Une empreinte stockée dans la même base ne protège pas contre un superutilisateur : le scellement doit être déposé hors base, et un compte PostgreSQL applicatif non privilégié reste à créer (opération soumise à autorisation).
