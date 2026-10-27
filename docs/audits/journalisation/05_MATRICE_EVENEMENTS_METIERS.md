# 05 — Matrice des événements auditables

*Date : 25/10/2026. Inventaire des événements journalisés par module, avec leur mode de capture, leur criticité et leur schéma.*

## 1. Schéma commun d'un événement (journal central `journaux_audit`)

| Champ | Contenu | Remarque |
|---|---|---|
| `id` | Séquence d'inscription | Ordre de chaînage des empreintes |
| `uuid` | Identifiant stable | Nouveaux événements |
| `code_evenement` | `MODULE.ACTE` (ex. `ENG.VISA`, `ORD.SIGNATURE`, `PAY.VALIDATION`, `EB.CREATED`) | Calculé par `AuditLogger` |
| `categorie` | `metier`, `securite`, `technique` | Préfixe d'action |
| `module` | EB, ENG, LIQ, ORD, PAY, PAP, BUDGET, NOMENCLATURE, REC, SE, TACHES, WORKFLOW, GED, UTILISATEURS, HABILITATIONS, ORGANISATION, DELEGATION, PARAMETRAGE, ALERTES, AUTHENTIFICATION, SECURITE, JOURNALISATION, SYSTEME… | Classe de l'objet, puis préfixe d'action |
| `action`, `resultat`, `gravite` | Action ; succès / refus / échec ; information → critique | |
| `user_id`, `user_nom`, `role`, `structure_id` | Auteur réel, nom figé, fonction(s) et unité **au moment de l'acte** | JNL-002, JNL-021 |
| `pour_compte_de` | Titulaire représenté (délégation, intérim) | JNL-010 |
| `auditable_type`, `auditable_id`, `reference_objet` | Objet métier et sa référence (numéro, code) | |
| `workflow_instance_id`, `workflow_tache_id` | Instance de circuit et tâche | JNL-009 |
| `correlation_id` | Dossier racine de la chaîne : `EB-{id}` (ou `ENG-{id}` sans EB) pour EB, ENG, LIQ, ORD, PAY et leurs tâches | Reconstitution EB → PAY |
| `request_id` | Identifiant de la requête HTTP (en-tête `X-Request-Id`) | Corrélation technique |
| `avant`, `apres`, `motif` | Valeurs avant/après (liste noire des secrets appliquée à toute profondeur), justification | JNL-008, JNL-011 |
| `occurred_at`, `recorded_at`, `effective_at` | `TIMESTAMPTZ(6)` UTC | § 5.1 |
| `source`, `ip`, `url`, `user_agent` | Provenance | |
| `empreinte` | SHA-256 de la forme canonique | § 9 |

## 2. Modes de capture

| Mode | Mécanisme | Transaction |
|---|---|---|
| **A — Automatique** | Trait `Auditable` sur 77 modèles : `created`, `updated` (avant/après des seuls champs modifiés), `deleted` | Même transaction que la mutation |
| **D — Décision de workflow** | `WorkflowAuditService` : un événement central par démarrage, validation, rejet, renvoi, avec acte, étape, rôle compétent, tâche, titulaire, et données sensibles (seuil ORD, reliquat PAY) ; plus une ligne dans `audit_workflow` | Même transaction que la décision |
| **E — Explicite** | `AuditLogger::evenement()` dans les services et contrôleurs | Celle de l'opération |
| **S — Journal spécialisé** | `login_logs`, `audit_workflow`, `workflow_tache_evenements`, `visas_electroniques`, `ged_journaux`, `audit_notifications`, `audit_reporting` | Propre à chaque module ; agrégés en lecture dans l'historique du dossier |

## 3. Matrice par module

| Module | Événement | Code / action | Mode | Criticité | Données clés |
|---|---|---|---|---|---|
| Authentification | Connexion réussie | `AUTHENTIFICATION.LOGIN` | E + S | Faible | Compte, IP |
| | Échec de connexion (identifiants, compte inactif, IP verrouillée) | `AUTHENTIFICATION.AUTH_ECHEC` | E + S | Élevée (refus) | Identifiant saisi, motif ; **jamais le mot de passe** |
| | Déconnexion | `AUTHENTIFICATION.LOGOUT` | E | Faible | |
| | Activation / désactivation MFA | `AUTHENTIFICATION.MFA_ACTIVE` / `MFA_DESACTIVE` | E | Faible | |
| | Changement / réinitialisation de mot de passe | `AUTHENTIFICATION.MOT_DE_PASSE_CHANGE` / `_REINITIALISE` | E | Faible | Événement seul, sans secret |
| Sécurité | Accès refusé (HTTP 403) | `SECURITE.ACCES_REFUSE` | E (gestionnaire d'exceptions) | Élevée | Méthode, chemin, motif ; une trace par acteur, chemin et minute |
| | Violation de séparation des fonctions | `WORKFLOW.SOD_VIOLATION` | E (hors transaction, persiste malgré le refus) | Critique | |
| Utilisateurs | Création, modification, statut | `UTILISATEURS.CREATED` / `UPDATED` / `UTILISATEUR_*` | A + E | Faible | Avant/après hors secrets |
| | **Attribution / retrait de rôles** | `UTILISATEURS.UTILISATEUR_ROLES_MODIFIES` | E (nouveau) | Modérée | Rôles avant/après |
| | **Profils, affectations aux structures** | `UTILISATEUR_PROFILS_MODIFIES`, `UTILISATEUR_STRUCTURES_MODIFIEES` | E (nouveau) | Faible | Listes avant/après |
| Rôles et permissions | Création, modification (permissions avant/après), suppression | `HABILITATIONS.ROLE_CREE` / `ROLE_MODIFIE` / `ROLE_SUPPRIME` | E | Modérée | |
| Délégations | Création, modification, fin de délégation et d'intérim | `DELEGATION.CREATED` / `UPDATED` | A (nouveau) | Faible | Titulaire, délégataire, structure, dates, motif |
| Référentiel organique | Structures, postes, fonctions | `ORGANISATION.*` | A | Information | |
| Paramétrage | Paramètres généraux | `PARAMETRAGE.*` | A | Information | |
| | **Seuil d'ordonnancement** (valeur, version, validité juridique) | `PARAMETRAGE.UPDATED` sur `ChaineDepenseParametre` | A (nouveau) | Modérée | Valeur et version avant/après (JNL-015) |
| | Circuits de workflow | `WORKFLOW.WORKFLOW_CIRCUIT_CREE` / `_MODIFIE` | E | Modérée | |
| PAP / planification | Piliers, axes, produits, sous-produits, activités, tâches, PAP, risques, mesures | `PAP.*`, `PAP.PAP_*` | A + E | Information | Avant/après |
| | Soumission, validation, rejet | `PAP.SOUMISSION` / `VALIDATION` / `REJET` | D | Modérée | Étape, rôle |
| Budget, nomenclature | Lignes budgétaires (montants ouverts, révisés), titres, articles, paragraphes, rubriques | `BUDGET.*`, `NOMENCLATURE.*` | A | Modérée | Montants avant/après (décimaux en chaîne) |
| | Révisions, imports, exercices (ouverture, clôture) | `BUDGET.BUDGET_REV_CREE`, `ADMINISTRATION.IMPORT_*`, `BUDGET.EXERCICE_*` | E | Modérée | |
| EB | Création, modification, suppression | `EB.CREATED` / `UPDATED` / `DELETED` | A | Information | Corrélation `EB-{id}` |
| | Soumission, validation (Directeur, Commissaire / SG, DRHMG, Président), retour, rejet | `EB.SOUMISSION`, `EB.VALIDATION` / `APPROBATION`, `EB.RETOUR`, `EB.REJET` | D | Modérée | Étape, rôle compétent, observation, titulaire |
| ENG | Création, montants, ligne, insuffisance de crédits, dégagement, annulation | `ENG.CREATED`, `ENG.ENGAGEMENT_INSUFFISANCE_CREDITS`, `ENG.ENGAGEMENT_DEGAGE`, `ENG.ENGAGEMENT_ANNULE` | A + E | Modérée | Crédits disponibles au contrôle |
| | Interventions de la Direction du Budget, visa ou refus du Contrôleur financier | `ENG.VALIDATION`, `ENG.VISA`, `ENG.REJET` | D | Modérée | Étape, rôle, corrélation |
| LIQ | Certification du service fait, visa du CF, rejet | `LIQ.CERTIFICATION`, `LIQ.VISA`, `LIQ.REJET` | D + A | Modérée | Deux actes distincts |
| ORD | Routage selon le seuil, signature SG ou Président, refus | `ORD.SIGNATURE`, `ORD.REJET` ; `ORD.ORDONNANCEMENT_ALERTE_FRACTIONNEMENT` | D + E | Modérée | **Montant, seuil, version du seuil, assiette, autorité compétente** |
| PAY | Contrôles comptables, autorisation de décaissement de l'Agent comptable, paiement partiel ou intégral, rejet d'instrument | `PAY.VALIDATION` (contrôles), `PAY.SIGNATURE` (autorisation), `PAY.REJET`, `PAY.PAIEMENT_INSTRUMENT_REJETE` | D + A + E | Modérée | **Montant, montant ordonnancé, cumul payé, reliquat**, mode de paiement ; un événement par paiement |
| Recettes | Prévisions, recettes, encaissements, contributions, plans d'apurement | `REC.*` | A + D | Information | |
| Suivi-évaluation | Collectes, mesures, missions, campagnes, indicateurs | `SE.*`, `SE.COLLECTE_VALIDEE` | A + E + D | Information | |
| Mes Tâches | Création, prise en charge, libération, décision | `workflow_tache_evenements` | S | Information | `pour_compte_de` |
| | Réaffectation, suspension, reprise, priorisation | `TACHES.TACHE_REAFFECTATION` / `_SUSPENSION` / `_REPRISE` / `_PRIORITE` | E (nouveau) + S | Faible | Ancien et nouvel acteur, motif |
| GED | Dépôt, version, téléchargement, suppression autorisée | `ged_journaux` | S | Information | Empreinte du document, pas de contenu binaire |
| Notifications | Émission, lecture, escalade | `audit_notifications` | S | Information | La suppression d'une notification n'affecte pas le journal |
| Alertes | Création, qualification, investigation, résolution, classement, clôture | `ALERTES.CREATED` / `UPDATED` | A (nouveau) | Selon l'alerte | Historique avant/après (JNL-022) |
| Système | Sauvegardes, restaurations (demande, validation, exécution), files d'attente | `SYSTEME.RESTAURATION_*`, `SYSTEME.QUEUE_*` | E | Modérée | |
| Journalisation | Scellement, vérification d'intégrité, anomalie, archivage, purge, export, décalage d'horloge | `JOURNALISATION.AUDIT_SCELLEMENT`, `AUDIT_VERIFICATION_INTEGRITE`, `AUDIT_ANOMALIE_INTEGRITE`, `AUDIT_ARCHIVAGE`, `AUDIT_PURGE`, `AUDIT_EXPORT`, `HORLOGE_DESYNCHRONISEE` | E (nouveau) | Information à critique | Le dispositif se journalise lui-même (JNL-020) |

## 4. Politique de capture

- **Décisions critiques** (visas, certifications, signatures, validations, rejets, changements de droits, seuil, restaurations) : toujours journalisées, dans la transaction de l'opération ; un échec d'écriture annule la décision (test REC-J21).
- **Consultations ordinaires** : non journalisées, à l'exception des refus d'accès, des exports et des téléchargements GED.
- **Secrets** : liste noire de clés (`password`, `*_token`, `*_secret`, `mfa_secret`, codes de récupération, clés privées) retirée à toute profondeur.
- **Montants** : conservés sous forme décimale exacte (chaînes), en XAF.
