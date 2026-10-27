# DESCRIPTION FONCTIONNELLE ET TECHNIQUE DÉTAILLÉE
## Module « Journalisation, Traçabilité et Horodatage » — BUDGET-CEEAC / GESBUDEP

**Version :** 1.0  
**Date :** 25 octobre 2026  
**Statut :** Spécification de référence à confronter à l'existant et aux procédures officiellement validées.

## 1. Présentation générale

### 1.1 Définition
Le module « Journalisation, Traçabilité et Horodatage » est le dispositif central d'enregistrement, conservation, consultation, analyse et contrôle des événements administratifs, budgétaires, financiers, documentaires, de sécurité et techniques survenant dans BUDGET-CEEAC / GESBUDEP. Il doit permettre de reconstituer l'histoire des opérations, sans dépendre de l'interface utilisée.

Chaque événement significatif répond à : **qui**, **quoi**, **quand**, **où**, **comment**, **sur quel objet**, **avec quelles valeurs avant/après**, **pourquoi**, **avec quel résultat** et **sous quelle autorité**.

### 1.2 Finalités et objectifs
- Retracer chaque décision métier critique et chaque transition de workflow.
- Enregistrer une référence temporelle fiable, normalisée en UTC.
- Identifier l'auteur réel, sa position et sa délégation éventuelle.
- Préserver le contexte des dossiers et les différences de données significatives.
- Garantir la cohérence transactionnelle des événements d'audit obligatoires.
- Détecter les anomalies, accès refusés et altérations potentielles.
- Protéger les journaux par des contrôles d'accès, des empreintes et des archives sécurisées.
- Produire des historiques et rapports accessibles uniquement aux personnes habilitées.

Un journal technique n'est pas à lui seul une preuve juridiquement infalsifiable : la valeur probante dépend également des dispositifs d'identification, de signature, de scellement, de conservation et du droit applicable.

## 2. Architecture et positionnement transversal

Le module doit s'intégrer aux domaines suivants : **PAP/planification**, **préparation budgétaire**, **Budget**, **EB**, **ENG**, **LIQ**, **ORD**, **PAY**, **Recettes**, **Suivi-évaluation**, **Mes Tâches**, **GED**, **Notifications**, **Administration**, **Utilisateurs/Rôles/Permissions**.

Trois catégories distinctes de journaux sont nécessaires :

| Catégorie | Objet | Exemples |
|---|---|---|
| Audit métier | Retracer les décisions et mutations administratives/financières | EB validée, ENG visé, ORD signé, PAY partiel |
| Sécurité | Retracer les événements d'identité, d'accès et de droits | Connexion refusée, permission retirée |
| Technique | Diagnostiquer l'infrastructure et l'application | Exception, job échoué, erreur d'API |

Les durées de conservation, données visibles et permissions varient selon la catégorie.

## 3. Périmètre des événements

| Domaine | Événements principaux |
|---|---|
| Authentification | Connexion, échec, déconnexion pertinente, révocation de session, changement de mot de passe (sans secret) |
| Utilisateurs | Création, modification, activation/désactivation, affectation |
| Rôles et permissions | Attribution, retrait, changement de portée, délégation |
| Référentiel organique | Structure, position, titulaire, rattachement |
| PAP et planification | Création, modification, soumission, validation, révision des piliers, axes, produits, sous-produits, activités et tâches programmatiques |
| Préparation budgétaire | Prévision, consolidation, arbitrage, validation, révision |
| Budget | Mouvement de crédits, révision de montant, décision et motif |
| EB | Création, correction, soumission, validation, rejet, retour, approbation |
| ENG | Contrôle de crédit, insuffisance, régularisation, visa ou refus |
| LIQ | Certification du service fait, contrôle documentaire, visa |
| ORD | Routage selon seuil, signature, refus, transfert |
| PAY | Préparation, paiement intégral/partiel, signature, reçu, rapprochement, régularisation |
| Recettes | Enregistrement, contrôle, correction, rapprochement |
| Suivi-évaluation | Modification des indicateurs et réalisations, validation des rapports |
| Mes Tâches | Création, affectation, prise en charge, délégation, décision, clôture |
| GED | Dépôt, version, consultation sensible, téléchargement, suppression autorisée |
| Paramétrage | Changement de seuils, workflows, périodes, règles ou sécurité |
| Système | Sauvegarde, restauration, export, vérification d'intégrité, archivage |

Les consultations ordinaires peuvent relever d'une journalisation sélective et proportionnée ; les décisions critiques doivent toujours être auditées.

## 4. Organisation fonctionnelle et navigation

Huit espaces :
1. **Tableau de bord** : indicateurs et répartition des événements.
2. **Journal des opérations** : liste paginée et filtrable.
3. **Journal de sécurité** : accès, changements d'habilitations et anomalies.
4. **Historique des dossiers** : chronologie et corrélations entre processus.
5. **Historique des utilisateurs** : interventions autorisées et contextualisées.
6. **Alertes et anomalies** : qualification et résolution des incidents.
7. **Rapports et exports** : restitution des événements et preuves disponibles.
8. **Administration** : rétention, intégrité, archivage et supervision.

### 4.1 Tableau de bord
Indicateurs : événements totaux, événements réussis/refusés, décisions financières, changements de droits, alertes, échecs de scellement, décalages temporels, retards de collecte et statut d'archivage. Chaque indicateur respecte le périmètre de consultation de l'utilisateur.

### 4.2 Journal central
Colonnes : référence, horodatage, catégorie, module, action, auteur, fonction historique, référence du dossier, résultat, gravité, statut d'intégrité, détails.

Filtres : période et heure, module, action, résultat, acteur, position, service/direction/département, référence, gravité, origine, délégation, IP autorisée, corrélation, état d'intégrité. Recherche, tri et pagination **côté serveur**.

### 4.3 Détail d'un événement
- Identification : identifiant, code, module, action et résultat.
- Temps : instant UTC, affichage local, fuseau, précision et référence de synchronisation.
- Acteur : identité réelle, fonction au moment de l'action, unité et délégation.
- Métier : dossier, étape de workflow, pièce ou décision liée.
- Modifications : champs audités et valeurs avant/après autorisées.
- Intégrité : empreinte, référence de scellement, corrélation, transaction et provenance.

## 5. Horodatage fiable

### 5.1 Modèle temporel
Distinguer :
- `occurred_at` : instant de survenance de l'action ;
- `recorded_at` : instant d'inscription durable au journal ;
- `received_at` : réception d'une source distante si applicable ;
- `effective_at` : date d'effet métier, éventuellement différente.

Conserver les instants techniques au format UTC en PostgreSQL `TIMESTAMPTZ` ; afficher à Libreville selon `Africa/Libreville` (UTC+01:00), en gardant le fuseau d'origine lorsqu'il est utile.

Exemple : `2026-10-25T08:42:18.325Z` = `25/10/2026 09:42:18.325` à Libreville.

### 5.2 Source d'heure et séquence
- Synchroniser les serveurs par NTP fiable et superviser les écarts.
- Ne jamais utiliser l'horloge du navigateur comme référence officielle d'une décision.
- Retenir une précision adéquate, microsecondes si la chaîne le permet.
- Utiliser un identifiant/séquence et une corrélation transactionnelle : deux événements peuvent partager le même instant.
- Signaler les périodes de désynchronisation plutôt que modifier rétroactivement les heures.

### 5.3 Actes sensibles
Horodater spécialement : visa du Contrôleur financier, certification LIQ, signature ORD, décision de paiement, modification d'un seuil, changement de permission, révision des crédits et restauration. Conserver les références à une signature ou un service d'horodatage électronique lorsqu'ils sont employés ; ne pas confondre horodatage technique et horodatage qualifié.

## 6. Intégration détaillée des modules métiers

### 6.1 PAP et planification
Tracer créations et révisions des piliers, axes, produits, sous-produits, activités, tâches programmatiques, indicateurs, responsables, périodes, budgets et validations. Les versions doivent permettre de retrouver le contexte programmatique d'une EB liée au PAP.

### 6.2 Budget et préparation budgétaire
Tracer exercice, ligne, unité, montants initiaux et révisés, mouvements de crédit, soumissions, arbitrages et justifications. Les montants doivent utiliser une représentation décimale exacte.

### 6.3 Expression de besoin (EB)
Tracer la création, les changements de données, soumission, retours, rejets et validations. Pour une EB PAP : validation du Directeur puis du Commissaire compétent ou du SG selon l'unité. Pour une EB hors PAP : DRHMG, SG puis Président, conformément aux procédures validées. Lier l'EB à l'engagement généré.

### 6.4 Engagement (ENG)
Tracer l'EB d'origine, la ligne budgétaire, les montants, les crédits disponibles au contrôle, la détection d'insuffisance, les régularisations, les interventions de la Direction Budget, le visa/refus du Contrôleur financier et la transition ENG → LIQ. Conserver tous les refus et tentatives, sans écraser l'historique.

### 6.5 Liquidation (LIQ)
Tracer les justificatifs, la certification du service fait par le service initiateur, les réserves, le visa du Contrôleur financier, les corrections et la transition LIQ → ORD. La certification et le visa sont deux actes distincts.

### 6.6 Ordonnancement (ORD)
Pour chaque routage/signature : référence, montant en XAF, seuil paramétré (référence 5 000 000 FCFA), version du paramètre, acteur compétent (SG si montant ≤ seuil ; Président si supérieur), délégation, signataire effectif, décision et transfert à l'Agence comptable. Un changement futur du seuil ne modifie pas les décisions historiques.

### 6.7 Paiement (PAY)
Tracer préparation, contrôle, moyen de paiement, paiement partiel ou intégral, décision/signature de l'Agent comptable, reçu, rapprochement, annulation/régularisation. Chaque paiement partiel possède son propre événement et le reliquat demeure vérifiable.

**Exemple :** 10 000 000 XAF ordonnancés ; paiement de 4 000 000 XAF ; reliquat de 6 000 000 XAF. L'événement du premier paiement ne doit pas indiquer que le dossier est entièrement payé.

### 6.8 Recettes et suivi-évaluation
Tracer les contrôles, corrections, recettes validées, valeurs cibles/réalisées, périodes de rapport, taux d'exécution, observations et validations.

### 6.9 Mes Tâches et workflows
Tracer création, affectation, notification pertinente, prise en charge, réaffectation, délégation, suspension/reprise, décision, clôture et création de la tâche suivante. Les événements portent les identifiants de tâche, d'étape, d'instance et de dossier. Utiliser `correlation_id` pour reconstituer l'ensemble de la chaîne EB → ENG → LIQ → ORD → PAY.

### 6.10 GED
Tracer dépôt, versionnement, documents générés, téléchargements sensibles, modifications, suppressions autorisées. Conserver les références et empreintes des documents, sans copier inutilement leur contenu binaire dans les journaux.

## 7. Traçabilité des changements et des décisions

Pour les champs audités, enregistrer les anciennes et nouvelles valeurs, la justification et la référence du dossier. Exemple :

```json
{
  "event_code": "BUDGET.CREDIT_UPDATED",
  "entity_reference": "BUD-2026-015",
  "changes": {
    "revised_amount": {"before": "12000000", "after": "15000000"}
  },
  "reason": "Révision budgétaire autorisée",
  "currency": "XAF"
}
```

Ne jamais enregistrer mots de passe, clés privées, jetons ou secrets. Définir une liste blanche des champs auditables et masquer les données sensibles selon les politiques. Pour les actes validés, préférer une annulation ou une contrepassation réglementée à l'effacement physique.

## 8. Sécurité, délégation et habilitations

Tracer connexions, refus, changements de mot de passe (événement seulement), modification des rôles/permissions, changements de titulaires et délégations. Pour une délégation, conserver titulaire, délégataire, compétence, périmètre, période, autorité et décisions effectuées.

Les permissions proposées, à adapter au RBAC existant :

```text
audit.dashboard.view
audit.events.view_own
audit.events.view_unit
audit.events.view_extended
audit.security.view
audit.alerts.view
audit.alerts.manage
audit.integrity.view
audit.integrity.execute
audit.reports.generate
audit.exports.download
audit.retention.view
audit.retention.manage
```

Les accès doivent respecter le cloisonnement organique de la CEEAC ; les accès de supervision n'accordent pas automatiquement des droits de décision. Un administrateur technique ne bénéficie pas implicitement d'un droit étendu de consultation financière.

## 9. Intégrité, scellement et inaltérabilité

- Utiliser un modèle logique append-only pour les événements d'audit.
- Protéger les écritures par des rôles PostgreSQL dédiés et des droits minimaux.
- Canonicaliser la représentation de chaque événement et calculer une empreinte SHA-256.
- Une chaîne d'empreintes peut suivre `H_i = SHA256(H_(i-1) || E_i)` avec ordre et sérialisation non ambigus.
- Sceller périodiquement les têtes de chaîne dans un stockage distinct, sécurisé et si possible immuable.
- Vérifier régulièrement les empreintes, séquences, archives et scellements.
- Une empreinte conservée uniquement dans la même base ne garantit **pas** l'infalsifiabilité face à un administrateur ayant tous les privilèges.

Les contrôles d'intégrité produisent un rapport horodaté : périmètre, nombre d'événements, résultat (conforme/anomalie/non vérifiable), références des scellements et anomalies. Ils ne réécrivent jamais les journaux testés.

## 10. Alertes et anomalies

Signaler : accès refusés répétés, changements inhabituels de permissions, délégation expirée utilisée, incohérence de workflow, doublons ou décisions concurrentes, rupture de chaîne d'empreintes, horloge désynchronisée, archivage échoué, collecte interrompue et volumes anormaux.

Niveaux : information, faible, modéré, élevé, critique. Cycle d'alerte : **détectée → qualifiée → en investigation → résolue/classée → clôturée**. Toutes les évolutions d'alerte sont tracées. Une escalade de sécurité ne doit pas modifier les droits métier par défaut.

## 11. Rapports et exports

Rapports attendus : activité par utilisateur/unité, décisions budgétaires, parcours EB/ENG/LIQ/ORD/PAY, modifications de droits, seuils, actions par délégation, incidents de sécurité, intégrité, archivage. Exports PDF, XLSX, CSV ou JSON, selon les permissions. Conserver date de génération, acteur exportant, périmètre, filtres, référence et éventuellement empreinte SHA-256 de l'export. Journaliser la génération et le téléchargement des rapports sensibles.

## 12. Architecture technique

| Couche | Technologie cible |
|---|---|
| Frontend | React, Vite, Tailwind CSS, Font Awesome 7 |
| Backend | Laravel 13+ et API REST |
| Base de données | PostgreSQL 18+ |
| Traitements différés | Laravel Queues/workers, Redis si prévu |
| Journaux techniques | Solution compatible PSR-3 et observabilité existante |
| Vérification | PHPUnit/Pest, tests API, Playwright |

Le service d'audit doit résider côté backend. Le frontend ne doit pas être la source d'autorité des événements métier. Réutiliser les services d'événements et journaux existants avant d'introduire de nouvelles couches.

### 12.1 Fiabilité transactionnelle
Dans une même transaction PostgreSQL pour chaque décision critique : vérifier les habilitations, enregistrer la décision, modifier le dossier, créer l'événement d'audit obligatoire, enregistrer l'outbox si nécessaire, puis valider. Après commit, notifier et traiter les exports/scellements secondaires de manière asynchrone et idempotente. Si l'audit obligatoire échoue, ne pas confirmer la décision.

### 12.2 Modèle conceptuel PostgreSQL
Tables possibles, à rapprocher des tables existantes :
- `audit_events` : événements centraux.
- `audit_event_changes` : différences avant/après.
- `audit_event_links` : relations inter-dossiers.
- `security_events` : accès et sécurité.
- `audit_integrity_seals` : scellements.
- `audit_integrity_checks` : vérifications.
- `audit_alerts` et `audit_alert_history` : alertes.
- `audit_export_requests` : exports.
- `audit_retention_policies` et `audit_archive_batches` : conservation.
- `audit_outbox` uniquement si aucune outbox commune adéquate n'existe.

Champs indicatifs de `audit_events` :

| Champ | Type indicatif | Usage |
|---|---|---|
| `id` | UUID | Identifiant stable |
| `event_sequence` | BIGINT | Ordre d'inscription |
| `event_code` | VARCHAR(150) | Type d'événement |
| `category`, `severity`, `module_code` | VARCHAR | Classification |
| `action`, `result` | VARCHAR | Action et résultat |
| `actor_type`, `actor_user_id` | VARCHAR, FK | Auteur réel |
| `actor_position_id`, `actor_org_unit_id` | FK | Fonction/unité historiques |
| `delegation_id` | FK nullable | Délégation |
| `subject_type`, `subject_id`, `subject_reference` | VARCHAR/UUID | Objet métier |
| `workflow_instance_id`, `workflow_task_id` | UUID nullable | Workflow |
| `reason` | TEXT | Justification |
| `occurred_at`, `recorded_at`, `effective_at` | TIMESTAMPTZ | Temps |
| `timezone_context` | VARCHAR | Fuseau métier |
| `request_id`, `correlation_id`, `transaction_id` | UUID/VARCHAR | Corrélation |
| `source_system`, `source_ip` | VARCHAR, INET nullable | Provenance |
| `metadata` | JSONB | Données autorisées |
| `payload_hash`, `previous_hash`, `chain_hash` | VARCHAR(64) | Intégrité |

Prévoir index temporels, sur auteur, objet, corrélation et workflow ; contraintes de valeurs, clés étrangères lorsque possible et partitionnement temporel en fonction du volume. Préserver la vérification des empreintes durant l'archivage.

### 12.3 API REST indicative

| Méthode | Route | Usage |
|---|---|---|
| GET | `/api/v1/audit/events` | Liste filtrée |
| GET | `/api/v1/audit/events/{id}` | Fiche détaillée |
| GET | `/api/v1/audit/dashboard` | Indicateurs |
| GET | `/api/v1/audit/subjects/{type}/{id}` | Historique d'un dossier |
| GET | `/api/v1/audit/users/{id}/events` | Historique acteur |
| GET | `/api/v1/audit/security` | Journal de sécurité |
| GET | `/api/v1/audit/alerts` | Alertes |
| POST | `/api/v1/audit/alerts/{id}/decisions` | Traiter une alerte |
| POST | `/api/v1/audit/integrity-checks` | Vérifier l'intégrité |
| GET | `/api/v1/audit/integrity-checks/{id}` | Consulter un contrôle |
| POST | `/api/v1/audit/exports` | Générer un export |
| GET | `/api/v1/audit/exports/{id}` | Consulter l'export |

Le navigateur ne doit pas pouvoir fabriquer librement des événements d'audit.

### 12.4 Organisation applicative indicative

```text
frontend/src/modules/journalisation/
  pages/                 # Dashboard, événements, sécurité, alertes, intégrité, rapports
  components/            # Filtres, table, fiche, chronologie, différences
  hooks/                 # Récupération et état des données
  services/auditApi.js

backend/app/
  Models/                # AuditEvent, AuditAlert, AuditIntegritySeal
  Services/Audit/        # Recorder, ContextResolver, Integrity, Search, Export, Retention
  Policies/              # AuditEventPolicy
  Http/Controllers/Api/  # AuditController
  Jobs/                  # Scellement, vérification, archivage
```

## 13. Conservation, archives, sauvegardes

Définir les durées selon les catégories, obligations applicables, délais de prescription, finalités d'audit et politiques institutionnelles : ne pas inventer une durée uniforme. Prévoir inventaires de lots, archivage vérifiable, recherche autorisée et restitution. Les restaurations de production nécessitent autorisation, sauvegarde, contrôle d'intégrité et rapport. Assurer la minimisation des données personnelles et la gestion de leur accès.

## 14. Performances, robustesse et contrôle interne

Exigences : journalisation obligatoire et durable, intégration transactionnelle, indexation adaptée, filtrage et pagination serveur, gestion des volumes, reprise des jobs, idempotence, supervision des échecs, séparation des fonctions et cloisonnement organique. Fixer les objectifs chiffrés à partir des mesures de charge et de l'infrastructure réelle.

## 15. Règles de gestion

| Code | Règle | Criticité |
|---|---|---|
| JNL-001 | Toute décision critique produit un événement d'audit. | Critique |
| JNL-002 | Chaque événement identifie son auteur réel ou service source. | Critique |
| JNL-003 | L'horodatage officiel ne dépend pas du navigateur. | Critique |
| JNL-004 | Les instants sont stockés avec référence UTC. | Critique |
| JNL-005 | Décision et audit obligatoire sont transactionnellement cohérents. | Critique |
| JNL-006 | L'historique n'est pas modifiable par les utilisateurs ordinaires. | Critique |
| JNL-007 | Rôles, permissions et délégations sont tracés. | Critique |
| JNL-008 | Les changements financiers audités conservent avant/après. | Critique |
| JNL-009 | Les événements sont corrélés aux dossiers et workflows. | Critique |
| JNL-010 | Le titulaire et le délégataire sont distingués. | Critique |
| JNL-011 | Aucun secret n'est journalisé. | Critique |
| JNL-012 | La consultation et les exports respectent les permissions. | Critique |
| JNL-013 | Les exports sensibles sont tracés. | Majeure |
| JNL-014 | Une anomalie d'intégrité déclenche une alerte. | Critique |
| JNL-015 | Les versions du seuil ORD sont historisées. | Critique |
| JNL-016 | Chaque paiement partiel est distinct. | Critique |
| JNL-017 | Annulations et contrepassations n'effacent pas l'original. | Critique |
| JNL-018 | Les traitements asynchrones sont idempotents. | Critique |
| JNL-019 | L'archivage maintient les preuves d'intégrité. | Critique |
| JNL-020 | Les changements de configuration de l'audit sont eux-mêmes tracés. | Critique |
| JNL-021 | Les fonctions et périmètres historiques restent interprétables. | Majeure |
| JNL-022 | Les alertes possèdent leur propre historique. | Majeure |
| JNL-023 | Les opérations de restauration sont enregistrées. | Critique |
| JNL-024 | Les données protégées ne sont pas altérées lors de l'implémentation. | Critique |

## 16. Recette fonctionnelle et technique

| Réf. | Test | Résultat attendu |
|---|---|---|
| REC-J01 | Création EB | Événement créé |
| REC-J02 | Changement de montant EB | Avant/après conservé |
| REC-J03 | Validation EB | Auteur, décision, étape, horaire |
| REC-J04 | Visa ENG | Journal lié au workflow |
| REC-J05 | Insuffisance de crédits | Anomalie et traitement retracés |
| REC-J06 | Certification LIQ | Certificateur identifié |
| REC-J07 | ORD au-dessus du seuil | Seuil et autorité compétente enregistrés |
| REC-J08 | PAY partiel | Montant et reliquat cohérents |
| REC-J09 | Réaffectation | Ancien/nouvel acteur |
| REC-J10 | Délégation | Acteur réel et titulaire |
| REC-J11 | Permission modifiée | Ancienne/nouvelle habilitation |
| REC-J12 | Accès interdit | Événement de sécurité |
| REC-J13 | Paramètre modifié | Valeurs/version conservées |
| REC-J14 | Double clic de décision | Pas de double décision |
| REC-J15 | Recherche historique | Seulement données autorisées |
| REC-J16 | Vérification sur logs intacts | Conforme |
| REC-J17 | Altération simulée en test | Détection d'anomalie |
| REC-J18 | Export audit | Export autorisé et tracé |
| REC-J19 | Décalage NTP simulé | Alerte |
| REC-J20 | Restauration isolée | Journaux/scellements cohérents |
| REC-J21 | Échec de l'audit obligatoire | Décision non confirmée |
| REC-J22 | Archivage d'un lot | Recherche et intégrité préservées |
| REC-J23 | Notification supprimée | Journal inchangé |
| REC-J24 | Changement de titulaire | Fonction historique conservée |
| REC-J25 | Consultation hors périmètre | Accès refusé |

Compléter par tests d'intégration Laravel/PostgreSQL, sécurité, concurrence, volumétrie et Playwright ; utiliser des jeux de données isolés.

## 17. Phases d'implémentation

1. **Audit sans modification** : identifier services, tables, événements, horloges et politiques existants.
2. **Conception complémentaire** : matrice des événements, politique d'audit, schéma et permissions.
3. **Horodatage et intégrité** : UTC, contrôle NTP, empreintes, scellements, vérifications.
4. **Intégrations métier** : PAP, Budget, EB, ENG, LIQ, ORD, PAY, Recettes, SE, Mes Tâches, GED.
5. **Interfaces** : consultation, chronologies, alertes, rapports, exports et administration.
6. **Recette et non-régression** : tests, protection des données, sécurité, conservation.

Ne pas réinitialiser les données existantes. Préserver Budget 2026, référentiel organisationnel, nomenclature, utilisateurs, mots de passe chiffrés, rôles, permissions et affectations. Tester les migrations sur une copie isolée avant leur application.

## 18. Livrables attendus

- Description fonctionnelle et technique détaillée (ce document).
- Matrice des événements auditables par module et action.
- Modèle de données et migrations contrôlées.
- Contrats d'API et règles d'autorisation.
- Politique d'horodatage, d'intégrité et de conservation.
- Rapport d'audit initial.
- Rapport de mise en conformité et d'implémentation.
- Rapport de tests et de non-régression.
- Procédure d'exploitation, d'archivage et de restauration.
- Guide d'administration et de consultation des journaux.

## 19. Résultats attendus et conclusion

GESBUDEP doit pouvoir reconstituer chaque opération importante, son auteur réel, sa date et son heure de référence, son contexte institutionnel, ses effets, ses validations, ses pièces associées et les événements liés à la chaîne de dépense. Le journal doit être durable, exploitable par les personnes compétentes et vérifiable quant à son intégrité.

**Exigence finale :** aucune décision financière ou administrative critique ne doit réussir sans événement d'audit obligatoire enregistré ; aucun utilisateur ordinaire ne doit pouvoir réécrire les historiques ; aucun mécanisme de journalisation ne doit détruire les données et fonctionnalités existantes.
