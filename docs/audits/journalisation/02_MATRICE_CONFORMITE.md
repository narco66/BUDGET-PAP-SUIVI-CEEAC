# 02 — Matrice de conformité : Journalisation, Traçabilité et Horodatage

*Date : 25/10/2026. Référentiel : `docs/Module_Journalisation_Tracabilite_Horodatage_BUDGET_CEEAC_GESBUDEP.md` v1.0. État initial : audit du 25/10/2026 (rapport 01). État final : après les lots J1 à J8 (rapport 04).*

**Légende** : ✅ Conforme · 🟡 Partiellement conforme · ❌ Non conforme · ⬜ Absente · ⚪ Non vérifiable · 🔷 Écart assumé.
Preuves : `JT:` = test de `tests/Feature/JournalisationTest.php` ; `PG:` = recette ou contrôle sur PostgreSQL (rapports 06 et 07).

## 1. Règles de gestion (§ 15)

| Code | Exigence | Initial | Final | Preuve / correction | Écart résiduel |
|---|---|---|---|---|---|
| JNL-001 | Décision critique ⇒ événement d'audit | 🟡 (mutations tracées, décisions non structurées) | ✅ | Événement central par décision de workflow (`WorkflowAuditService`) ; `JT:test_rec_j03_*`, `PG:REC-J03` | — |
| JNL-002 | Auteur réel ou service source | ✅ | ✅ | `user_id`, `user_nom` figé, `role` figé, « Système » pour la console | — |
| JNL-003 | Horodatage indépendant du navigateur | ✅ | ✅ | `now('UTC')` serveur | — |
| JNL-004 | Instants avec référence UTC | ❌ (session SQL en UTC+1, `timestamp` sans fuseau) | ✅ | Session PG en UTC ; `TIMESTAMPTZ(6)` ; `PG:` microseconde | Lignes historiques en `timestamp(0)` (non réécrites) |
| JNL-005 | Décision et audit cohérents transactionnellement | 🟡 (non démontré) | ✅ | `JT:test_rec_j21_*` : échec du journal ⇒ décision annulée | — |
| JNL-006 | Historique non modifiable par les utilisateurs | ❌ (aucune protection) | 🟡 | Gardes applicatives + déclencheurs PG ; `JT:test_jnl006_*`, `PG:` UPDATE/DELETE refusés | Compte PG superutilisateur (soumis à autorisation) |
| JNL-007 | Rôles, permissions, délégations tracés | 🟡 (rôles d'un utilisateur et délégations non tracés) | ✅ | `utilisateur_roles_modifies` avant/après, délégations et intérims audités ; `JT:test_rec_j11_*`, `JT:test_rec_j10_*` | — |
| JNL-008 | Changements financiers : avant/après | ✅ | ✅ | `Auditable` ; `JT:test_rec_j02_*` | — |
| JNL-009 | Corrélation dossiers et workflows | ❌ | ✅ | `correlation_id` EB → PAY, `workflow_instance_id`, `workflow_tache_id`, `request_id` ; `JT:test_rec_j07_*`, `JT:test_rec_j08_*` | — |
| JNL-010 | Titulaire et délégataire distingués | 🟡 (dans les tâches seulement) | ✅ | `pour_compte_de` au journal central ; `JT:test_rec_j10_*` | — |
| JNL-011 | Aucun secret journalisé | 🟡 (2 clés masquées) | ✅ | Liste noire récursive ; `JT:test_jnl011_*`, `JT:test_echec_de_connexion_*` | — |
| JNL-012 | Consultation et exports selon permissions | ❌ (journal ouvert à 12 rôles) | ✅ | `AuditAccesService` ; `JT:test_rec_j15_*`, `PG:REC-J15` | — |
| JNL-013 | Exports sensibles tracés | ⬜ | ✅ | `audit_exports` + événement ; `JT:test_rec_j18_*` | — |
| JNL-014 | Anomalie d'intégrité ⇒ alerte | ⬜ | ✅ | Alerte critique `INTEGRITE_JOURNAL` ; `JT:test_rec_j17_*` | — |
| JNL-015 | Versions du seuil ORD historisées | 🟡 (version sur l'ORD, paramètre non audité) | ✅ | Paramètre audité ; seuil et version dans l'événement de signature ; `JT:test_rec_j13_*`, `JT:test_rec_j07_*` | — |
| JNL-016 | Chaque paiement partiel distinct | 🟡 | ✅ | Un événement par paiement avec reliquat ; `JT:test_rec_j08_*` | — |
| JNL-017 | Annulations sans effacement de l'original | 🟡 | ✅ | Journal en ajout seul ; annulations tracées comme nouveaux événements | Suppressions physiques métier existantes tracées (`deleted` avec avant) |
| JNL-018 | Traitements asynchrones idempotents | 🟡 | 🟡 | Scellement incrémental idempotent ; notifications `afterCommit` (existant) | Pas d'outbox dédiée au journal |
| JNL-019 | Archivage avec preuves d'intégrité | ⬜ (purge sans archive) | ✅ | `audit:journal archiver`, vérification par archive ; `JT:test_rec_j22_*`, `JT:test_purge_*_refusee_sans_archive` | — |
| JNL-020 | Configuration de l'audit tracée | ❌ (purge non tracée) | ✅ | Scellement, vérification, archivage, purge, export journalisés | Paramètres de `config/audit.php` modifiables hors application (fichier `.env`) |
| JNL-021 | Fonctions et périmètres historiques interprétables | 🟡 (rôle figé) | ✅ | `role` + `structure_id` figés | — |
| JNL-022 | Historique propre des alertes | ⬜ | ✅ | `Alerte` auditée, cycle de traitement ; `JT:test_alerte_*` | — |
| JNL-023 | Restaurations enregistrées | ✅ | ✅ | Existant (`restauration_*`) | — |
| JNL-024 | Données protégées non altérées | — | ✅ | Empreintes identiques (rapport 09) | — |

## 2. Fonctionnalités (§ 2 à § 13)

| Exigence | Initial | Final | Preuve / écart |
|---|---|---|---|
| § 2 Trois catégories de journaux | 🟡 (tables séparées, non classées) | ✅ | `categorie` métier / sécurité / technique ; journaux spécialisés conservés |
| § 3 Périmètre des événements (18 domaines) | 🟡 | 🟡 | 16/18 couverts (matrice 05) ; Recettes et SE couverts par mutations sans événement de décision dédié hors workflow |
| § 4.1 Tableau de bord | ⬜ | ✅ | `/audit/tableau-de-bord`, périmètre respecté |
| § 4.2 Journal central, filtres, tri et pagination serveur | 🟡 (2 filtres) | 🟡 | 11 filtres serveur ; filtres « position » et « IP » non proposés ; événements antérieurs non recherchables par référence |
| § 4.3 Détail d'un événement | ⬜ | ✅ | `/audit/{id}` : identification, temps, acteur, métier, avant/après, intégrité, corrélation |
| § 4 Journal de sécurité | 🟡 (connexions seules) | ✅ | `/audit/securite` |
| § 4 Historique des dossiers | ⬜ | ✅ | `/audit/dossiers/{type}/{id}` (journal + workflow + tâches + visas) |
| § 4 Historique des utilisateurs | ⬜ | ✅ | `/audit/utilisateurs/{id}` |
| § 4 Alertes et anomalies | ⬜ | 🟡 | Registre existant + cycle via API ; pas d'écran dédié au cycle (écran des alertes existant) |
| § 4 Rapports et exports | ⬜ | 🟡 | CSV et JSON tracés ; PDF et XLSX non réalisés |
| § 4 Administration (rétention, intégrité, archivage) | ⬜ | 🟡 | Écran Intégrité ; archivage et rétention par commande et configuration, sans écran |
| § 5.1 Quatre instants, UTC, affichage Libreville | ❌ | ✅ | Rapport 06 |
| § 5.2 NTP, précision, séquence, désynchronisation signalée | ⬜ | ✅ | `HorlogeService` ; `PG:` NTP synchronisé, écart 0,032 s |
| § 5.3 Actes sensibles horodatés, horodatage qualifié non confondu | 🟡 | ✅ | Codes VISA / CERTIFICATION / SIGNATURE ; mention explicite à l'écran |
| § 6.1–6.10 Intégration des modules | 🟡 | ✅ | Matrice 05 |
| § 7 Avant/après, liste blanche, masquage | 🟡 | 🟡 | Avant/après et masquage ✅ ; **liste noire** des secrets plutôt que liste blanche des champs |
| § 8 Sécurité, délégation, 13 permissions, cloisonnement | ❌ | 🟡 | Alias des 13 permissions, cloisonnement ✅ ; administrateur doté de tous les droits (`Gate::before`, soumis à autorisation) |
| § 9 Append-only, droits PG, SHA-256, chaînage, scellement, vérification | ⬜ | 🟡 | Tout est en place ; compte PG superutilisateur et dépôt local (soumis à autorisation) |
| § 10 Alertes (types, 5 niveaux, cycle) | ⬜ | 🟡 | Intégrité, horloge, accès refusés tracés ; détections « refus répétés », « volumes anormaux », « collecte interrompue » non automatisées ; niveaux du registre existant (information → urgence) |
| § 11 Rapports (périmètre, acteur, empreinte) | ⬜ | 🟡 | Voir § 4 exports |
| § 13 Conservation, archives, restauration | ❌ | 🟡 | Purge désactivée, archivage vérifiable ✅ ; durées non validées institutionnellement ; restitution d'archive par lecture du fichier |
| § 14 Performances, robustesse | 🟡 | 🟡 | Index ajoutés, pagination serveur ; pas de partitionnement ni de mesures de charge |

## 3. Exigences techniques (§ 12)

| Exigence | Initial | Final | Preuve / écart |
|---|---|---|---|
| Frontend React, Vite, Tailwind, Font Awesome 7 | ❌ | 🔷 | Blade + Bootstrap conservés (interdiction de reconstruire) |
| Backend Laravel, API REST | 🟡 | ✅ | 12 routes `api/v1/audit/*` |
| PostgreSQL 18 | ✅ | ✅ | — |
| Service d'audit côté serveur, navigateur sans autorité | ✅ | ✅ | Aucune route d'écriture ; `JT:test_api_lecture_seule_et_pages` (POST ⇒ 405) |
| § 12.1 Transaction unique (décision + audit) | 🟡 | ✅ | `JT:test_rec_j21_*` |
| § 12.2 Modèle de données (11 tables indicatives) | ⬜ | 🟡 | Consolidation : `journaux_audit` (= `audit_events`), `audit_scellements`, `audit_controles_integrite`, `audit_exports`, `audit_archives`, `alertes` ; `audit_event_changes` et `audit_event_links` non créées (avant/après et corrélation portés par l'événement) ; pas de politiques de rétention en table |
| § 12.3 API indicative | ⬜ | ✅ | 12/12 routes |
| Vérification PHPUnit, API, Playwright | 🟡 | 🟡 | PHPUnit et API ✅ ; Playwright non installé (captures Chrome à la place) |

## 4. Recette (§ 16)

| Réf. | Test | Final | Preuve |
|---|---|---|---|
| REC-J01 | Création EB | ✅ | `JT:test_rec_j01_*`, `PG` |
| REC-J02 | Changement de montant EB | ✅ | `JT:test_rec_j02_*` |
| REC-J03 | Validation EB | ✅ | `JT:test_rec_j03_j14_*`, `PG` |
| REC-J04 | Visa ENG lié au workflow | ✅ | Même mécanisme (code `ENG.VISA` déterminé par l'étape « Visa Contrôleur financier ») ; couvert par REC-J03 et REC-J07 |
| REC-J05 | Insuffisance de crédits | ✅ | Événement existant `engagement_insuffisance_credits` (classé ENG) |
| REC-J06 | Certification LIQ | ✅ | Code `LIQ.CERTIFICATION` (étape « Certification du service fait ») |
| REC-J07 | ORD au-dessus du seuil | ✅ | `JT:test_rec_j07_*` |
| REC-J08 | PAY partiel | ✅ | `JT:test_rec_j08_*` |
| REC-J09 | Réaffectation | ✅ | `JT:test_rec_j09_*` |
| REC-J10 | Délégation | ✅ | `JT:test_rec_j10_*` |
| REC-J11 | Permission modifiée | ✅ | `JT:test_rec_j11_*` |
| REC-J12 | Accès interdit | ✅ | `JT:test_rec_j12_j25_*`, `PG` |
| REC-J13 | Paramètre modifié | ✅ | `JT:test_rec_j13_*` |
| REC-J14 | Double clic de décision | ✅ | `JT:test_rec_j03_j14_*` |
| REC-J15 | Recherche historique dans le périmètre | ✅ | `JT:test_rec_j15_*`, `PG` |
| REC-J16 | Vérification sur journal intact | ✅ | `JT:test_rec_j16_*`, `PG:CTRL-20261025-061759-2BA9F2` |
| REC-J17 | Altération simulée | ✅ | `JT:test_rec_j17_*`, `JT:test_suppression_*` |
| REC-J18 | Export audit | ✅ | `JT:test_rec_j18_*`, `PG` |
| REC-J19 | Décalage NTP simulé | ✅ | `JT:test_rec_j19_*` |
| REC-J20 | Restauration isolée | ⚪ | Non exécutée (restauration de base non autorisée dans cet environnement) |
| REC-J21 | Échec de l'audit obligatoire | ✅ | `JT:test_rec_j21_*` |
| REC-J22 | Archivage d'un lot | ✅ | `JT:test_rec_j22_*` |
| REC-J23 | Notification supprimée | ✅ | `JT:test_rec_j23_*` |
| REC-J24 | Changement de titulaire : fonction historique conservée | ✅ | `role` et `structure_id` figés à l'écriture |
| REC-J25 | Consultation hors périmètre refusée | ✅ | `JT:test_rec_j15_*`, `JT:test_rec_j12_j25_*` |

## 5. Synthèse chiffrée

| | ✅ | 🟡 | ❌ | ⬜ | ⚪ | 🔷 | Total |
|---|---|---|---|---|---|---|---|
| Règles de gestion | 22 | 2 | 0 | 0 | 0 | 0 | 24 |
| Fonctionnalités | 10 | 12 | 0 | 0 | 0 | 0 | 22 |
| Techniques | 5 | 2 | 0 | 0 | 0 | 1 | 8 |
| Recette | 24 | 0 | 0 | 0 | 1 | 0 | 25 |
| **Total** | **61** | **16** | **0** | **0** | **1** | **1** | **79** |

**Taux de conformité final : 61/79 (77 %) pleinement conformes ; 77/79 (97 %) conformes ou partiellement conformes ; aucune exigence non conforme ou absente.** Hors élément non vérifiable et écart assumé : 61/77 (79 %). Règles de gestion : 22/24 (92 %).

**État initial (54 lignes hors recette, même grille)** : 6 conformes (11 %), 21 partiellement conformes, 9 non conformes, 17 absentes, 1 sans objet.
