# 02 — Matrice de conformité du module « Mes Tâches »

*Date : 25/10/2026. Référentiel : `Module_Mes_Taches_BUDGET_CEEAC_GESBUDEP.md` v1.0 (§ 4 à § 24). État initial : audit du 25/10/2026 (rapport 01). État final : après les lots L1 à L8 (rapport 04).*

**Légende** : ✅ Conforme (vérifié par test ou contrôle) · 🟡 Partiel · ❌ Non conforme · ⚪ Non vérifiable (ressource absente) · 🔷 Écart assumé (décision d'architecture documentée).

Les preuves renvoient aux fichiers du dépôt et aux tests de `tests/Feature/MesTachesTest.php` (notés `MT:<nom>`) ou à la recette PostgreSQL (`PG:<contrôle>`, rapport 05).

## 1. Règles de gestion (§ 20)

| Règle | Libellé | Initial | Final | Preuve de l'état final |
|---|---|---|---|---|
| RG-01 | Tâche issue d'un événement métier autorisé | ❌ (pas d'entité tâche) | ✅ | `TacheWorkflowService::synchroniser()` appelé uniquement par le moteur (démarrage, validation, renvoi, `agir`) ; aucune création en consultation. `MT:test_rec01_…` |
| RG-02 | Tâche liée à un dossier et une étape | ❌ | ✅ | Colonnes `objet_type/objet_id`, `workflow_instance_etape_id` non nulles ; index unique partiel « une tâche ouverte par étape » |
| RG-03 | Affectation selon le référentiel | 🟡 | ✅ | Rôle de l'étape + structure du dossier + suppléance organique (`TacheQueryService::eligibilite`) |
| RG-04 | Visibilité limitée aux tâches autorisées | 🟡 | ✅ | `peutConsulter`, `peutVoirUnite`, périmètre `structuresAutorisees`. `MT:test_rec10_…` |
| RG-05 | Décision réservée à l'acteur compétent | 🟡 (admin) | 🟡 | Acteur compétent vérifié (policy + SoD + étape attendue). Réserve : le moteur garde le pouvoir implicite de l'administrateur (A8, soumis à autorisation) ; l'administrateur est en revanche retiré de la file (`MT:test_l_administrateur_…`) |
| RG-06 | Décision et transition synchronisées | ❌ | ✅ | `cloturerEtape` dans la même transaction que la décision du moteur |
| RG-07 | Pas de double validation d'une tâche close | ❌ (A3) | ✅ | `lockForUpdate` + `etapeAttendueId` + `version`. `MT:test_rec12_…`, `PG:REC-12` |
| RG-08 | Audit des décisions et transitions | 🟡 | ✅ | `workflow_tache_evenements` (immuable : `updating`/`deleting` lèvent une exception) + `audit_workflow` existant |
| RG-09 | Contrôles métier non contournables | ❌ (V1, V2) | ✅ | `DecisionMetierService` unique pour les 5 points d'entrée. `MT:test_v1_controle_du_rib_…` |
| RG-10 | Données financières issues du module source | — | ✅ | `montant()` et fiche lisent le dossier et l'imputation à la volée ; rien n'est recopié dans la tâche |
| RG-11 | Lecture de notification sans effet sur la tâche | ✅ | ✅ | `MT:test_rec16_rg11_…` |
| RG-12 | Délégation limitée en date et périmètre | 🟡 | 🟡 | Dates contrôlées (`MT:test_rec11_…`), titulaire distinct de l'exécutant (`pour_compte_de`). Réserve : la table `delegations` n'a ni périmètre par module/acte ni justificatif (évolution de schéma non réalisée) |
| RG-13 | Retards visibles jusqu'à résolution | 🟡 | ✅ | `enRetard()`, carte « En retard », ligne rouge, priorité relevée automatiquement |
| RG-14 | Protection contre décisions concurrentes | ❌ | ✅ | Voir RG-07 ; HTTP 409 `TacheConflitException`. `MT:test_decision_api_obsolete_…` |
| RG-15 | Statistiques et exports selon permissions | ❌ (inexistant) | ✅ | `MT:test_rg15_statistiques_et_export_…` |
| RG-16 | Seuil d'ordonnancement paramétré et historisé | ✅ | ✅ | Existant conservé. `MT:test_rec07_rec08_…` |
| RG-17 | Paiements partiels et reliquats tracés | ✅ | ✅ | Reliquat affiché sur la fiche. `MT:test_rec09_…` |
| RG-18 | Historique consultable selon habilitations | 🟡 | ✅ | `GET /api/v1/tasks/{id}/history`, chronologie de la fiche, contrôlés par `peutConsulter` |
| RG-19 | Création et transition idempotentes | ❌ | ✅ | `synchroniser` idempotent, index unique partiel, `IdempotenceActionService` sur la décision API, commande de reprise rejouable (`MT:test_commande_de_reprise_…`) |
| RG-20 | Pas de données fictives en interface | 🟡 | ✅ | Indicateurs, listes, pièces calculés en base ; aucun jeu codé en dur |

## 2. Fonctionnalités (§ 4 à § 13)

| Exigence | Initial | Final | Preuve / réserve |
|---|---|---|---|
| § 4.1 En-tête (identité, fonction, unité, délégation, nombre actif, heure, recherche, actualisation) | ❌ | ✅ | `resources/views/taches/index.blade.php` (actualisé à HH:MM, bouton Actualiser) |
| § 4.2 Cartes d'indicateurs (9 indicateurs) | ❌ | ✅ | `TacheQueryService::statistiques()` : à traiter, en cours, urgentes, retard, reçues, terminées, retournées, par délégation, délai moyen, taux dans les délais |
| § 4.3 Bannière d'étape | ❌ | ✅ | `taches/show.blade.php` ; capture `mt_fiche.png` |
| § 4.4 Liste (12 colonnes) | 🟡 | ✅ | Référence, module, objet, étape, unité, affectataire, réception, échéance, priorité, montant, statut, actions |
| § 4.5 Filtres combinables | ❌ | 🟡 | Module, étape, statut, priorité, échéance, unité, période, montant, affectation, recherche texte. Réserve : pas de filtre « responsable » nominatif ; recherche limitée à référence, objet, étape (pas de bénéficiaire ni ligne budgétaire) |
| § 4.5 Recherche/tri/pagination côté serveur, critères conservés | ❌ | 🟡 | Côté serveur ✅ et critères dans l'URL ✅. Réserve : les filtres SQL précèdent un filtrage d'éligibilité en PHP (inévitable : SoD et suppléance ne sont pas exprimables en SQL) ; à surveiller en volumétrie |
| § 5.2 États (à traiter, en cours, suspendue, terminée, annulée) ; statut distinct de la décision | ❌ | ✅ | Enum `statut` + colonne `decision` distincte |
| § 5.3 Transitions | ❌ | 🟡 | Prendre en charge, décider, suspendre, reprendre, réaffecter, consulter ✅. Annulation : automatique (circuit clos, étape devenue sans objet) ; pas d'annulation manuelle motivée |
| § 6.2 Modes d'affectation | 🟡 | ✅ | Par position (rôle), par groupe avec prise en charge atomique, par délégation, réaffectation motivée. Nominative : via `assigne_a` |
| § 6.3 Cloisonnement ; périmètre ≠ pouvoir de décider | 🟡 | ✅ | `peutVoirUnite` séparé de `peutTraiter` ; `peutGererAffectations` séparé de `peutSuperviser` |
| § 6.4 Concurrence | ❌ | ✅ | Verrou + version |
| § 7 Fiche détaillée (6 blocs) ; crédits ; montants payés/restants | ❌ | 🟡 | 6 blocs ✅, crédits/engagé/disponible ✅, ordonnancé/payé/reste ✅. Réserve : « pièces requises / manquantes » non calculées (seules les pièces jointes sont listées) |
| § 8 Actions et contrôles préalables | 🟡 | 🟡 | Actions calculées côté serveur (`available_actions`) ; libellés Viser/Certifier/Signer/Approuver. Réserves : « Modifier » renvoie à l'écran du dossier ; « Demander un complément » = Retourner, limité aux circuits où le renvoi est prévu ; pas de réauthentification pour actes sensibles (hors MFA de connexion existant) |
| § 9 Intégration EB, ENG, LIQ, ORD, PAY | 🟡 | ✅ | Toutes les décisions passent par `DecisionMetierService`. REC-01/02/07/08/09 testés ; REC-03 à 06 couverts par `ChaineDepense*ConformiteTest` existants |
| § 10.1–10.4 PAP, Budget, SE, Recettes | 🟡 | ✅ | Tâches créées pour tous les circuits du moteur (`module()`) |
| § 10.5 GED | 🟡 | ✅ | Liens `pieces.download` et `ged.documents.show` depuis la fiche |
| § 10.6 Notifications cliquables vers la tâche | 🟡 | ✅ | `WorkflowNotificationService` → `route('taches.show')`. `PG:Notification cliquable` |
| § 11.1 Priorités paramétrables, priorisation motivée | ❌ | 🟡 | 4 niveaux, priorisation motivée et journalisée, relèvement par échéance. Réserve : règles de calcul codées (retard → urgente, ≤ 24 h → haute), non paramétrables en base |
| § 11.2 Échéances (jours ouvrés, calendrier, temps imputable) | 🟡 | 🟡 | Échéance par étape (`delai_jours`) existante. Réserve : ni jours ouvrés ni calendrier institutionnel ; temps de suspension non déduit |
| § 11.3 Escalades | 🟡 | 🟡 | Relances SLA existantes (`workflow:relances`) conservées. Réserve : pas d'escalade paramétrable au superviseur |
| § 12 Délégations | 🟡 | 🟡 | Voir RG-12 |
| § 13 Supervision et rapports | ❌ | 🟡 | `/mes-taches/supervision` : par module, rôle, unité, acteur, décisions, délais moyen et médian par étape. Réserve : export CSV seulement (Excel et PDF non réalisés) ; pas de rapport « causes de blocage » |

## 3. Exigences techniques et non fonctionnelles (§ 14 à § 19)

| Exigence | Initial | Final | Preuve / réserve |
|---|---|---|---|
| § 14 React + Tailwind + Font Awesome 7 | ❌ | 🔷 | Écart assumé : Blade/Bootstrap (UI principale) et Vue 3 (SPA) existants conservés (interdiction de reconstruire) ; Bootstrap Icons |
| § 15 Tables `workflow_tasks`, événements | ❌ | ✅ | `workflow_taches`, `workflow_tache_evenements` (nommage français du projet) ; migration additive |
| § 15.3 Contraintes et index | ❌ | ✅ | Référence unique, index unique partiel, index statut/rôle/structure/échéance |
| § 16 API REST | 🟡 | ✅ | `api/v1/tasks/*` (16 routes) ; format de réponse aligné sur § 16.1 (`available_actions`, `version`) |
| § 16.2 Transaction d'une décision | ❌ | ✅ | Verrou tâche → policy → service métier → moteur → clôture → journal, en une transaction |
| § 17 Sécurité, SoD, audit, référentiels protégés | 🟡 | ✅ | SoD (`motifRefus`), journal immuable, empreintes sans écart (rapport 07) |
| § 18 Figma | ⚪ | ⚪ | Aucune maquette dans `/docs/` : non vérifiable. Conventions visuelles de l'application suivies (sidebar, cartes, badges) |
| § 18 Accessibilité, états vides, boutons applicables seuls | 🟡 | 🟡 | États vides, libellés, boutons conditionnés ✅. Pas d'audit d'accessibilité outillé |
| § 19 Performance p95 < 2 s | ⚪ | ⚪ | Non mesuré en charge (pas d'outil de charge exécuté) |
| § 19 Fiabilité, concurrence, disponibilité | ❌ | ✅ | Transactions, verrous, reprise idempotente |
| § 19 Maintenabilité (services, tests) | 🟡 | ✅ | 5 services dédiés, 18 tests |

## 4. Plan de recette (§ 22)

| Cas | Final | Preuve |
|---|---|---|
| REC-01 Soumission EB PAP | ✅ | `MT:test_rec01_…`, `PG:REC-01` |
| REC-02 Validation Directeur | ✅ | `MT:test_rec02_rec13_…`, `PG:REC-02` |
| REC-03 EB hors PAP | ✅ | `ChaineDepenseEbConformiteTest` (existant) |
| REC-04 Visa d'engagement | ✅ | `ChaineDepenseAvalConformiteTest` (existant) |
| REC-05 Crédits insuffisants | ✅ | `ChaineDepenseAvalConformiteTest`, `DepenseTest` (existants) |
| REC-06 Certification liquidation | ✅ | `ChaineDepenseAvalConformiteTest` (existant) |
| REC-07 / REC-08 ORD selon seuil | ✅ | `MT:test_rec07_rec08_…` |
| REC-09 Paiement partiel | ✅ | `MT:test_rec09_…` |
| REC-10 Non habilité | ✅ | `MT:test_rec10_…` |
| REC-11 Délégation expirée | ✅ | `MT:test_rec11_…` |
| REC-12 Deux validations simultanées | ✅ | `MT:test_rec12_…`, `PG:REC-12` |
| REC-13 Clôture | ✅ | `MT:test_rec02_rec13_…` |
| REC-14 Historique | ✅ | `PG:REC-14` |
| REC-15 Recherche filtrée | ✅ | `MT:test_rg15_…`, `MT:test_pages_blade_et_api_listes` |
| REC-16 Notification cliquable | ✅ | `MT:test_rec16_…`, `PG:Notification cliquable` |
| REC-17 Incident asynchrone | 🟡 | Reprise idempotente testée ; pas de test d'injection de panne en file asynchrone |
| REC-18 Export | ✅ | `MT:test_rg15_…` |
| REC-19 Seeding de démonstration | ✅ | Aucun seeder exécuté ; empreintes sans écart (rapport 07) |
| REC-20 Conformité visuelle | ⚪ | Maquettes absentes |

## 5. Synthèse chiffrée

Décompte des **72 lignes** évaluées ci-dessus : 20 règles de gestion, 22 exigences fonctionnelles, 11 exigences techniques, 19 lignes de recette (REC-07 et REC-08 regroupés). Les critères du § 24 sont couverts par ces lignes.

| | ✅ | 🟡 | ❌ | ⚪ | 🔷 |
|---|---|---|---|---|---|
| Règles de gestion (20) | 18 | 2 | 0 | 0 | 0 |
| Fonctionnalités (22) | 12 | 10 | 0 | 0 | 0 |
| Techniques (11) | 7 | 1 | 0 | 2 | 1 |
| Recette (19) | 17 | 1 | 0 | 1 | 0 |
| **Total (72)** | **54** | **14** | **0** | **3** | **1** |

Taux de conformité final : **54/72 lignes pleinement conformes (75 %)**, **68/72 conformes ou partiellement conformes (94 %)**, aucune non-conformité ouverte. Hors éléments non vérifiables et écart assumé, le taux de conformité pleine est de **54/68 (79 %)**. Les règles de gestion sont conformes à 90 % (18/20) ; les deux réserves (RG-05, RG-12) relèvent d'une opération soumise à autorisation ou d'une évolution du schéma des délégations.

État initial (même grille) : parmi les règles de gestion, seules RG-11, RG-16 et RG-17 étaient conformes, grâce aux modules métier existants ; 8 étaient non conformes (notamment l'absence d'entité tâche, la double validation possible A3 et les contournements V1/V2), 8 partiellement conformes et 1 sans objet.
