# Manuel utilisateur — Planification stratégique

**Application :** BUDGET-PAP-SUIVI-CEEAC (CEEAC)  
**Module :** Planification stratégique institutionnelle  
**Public :** Secrétariat général, directions, commissaires, planificateurs, contrôleurs de gestion  
**Mise à jour :** 26 octobre 2026

---

## 1. Objectif du module

Le module **Planification stratégique** constitue le **niveau supérieur de pilotage** de la performance de la Commission de la CEEAC. Il permet de :

- formaliser la **Vision** et les **Plans stratégiques communautaires** ;
- décliner les **orientations**, **programmes** et **projets** stratégiques ;
- gérer le **portefeuille de projets** et la **programmation pluriannuelle / annuelle** ;
- définir **objectifs**, **indicateurs** et **risques** stratégiques ;
- faire **valider les plans** par un circuit formel (Direction puis Secrétariat général) ;
- assurer l’**alignement** avec le référentiel RBM/GAR et le **PAP** ;
- produire des **rapports de cohérence**, un **tableau de pilotage décisionnel** et des **versions officielles archivées**.

### Chaîne institutionnelle cible

```
Vision
  └── Plan stratégique
        └── Orientations stratégiques
              └── Programmes stratégiques
                    └── Projets stratégiques
                          └── Portefeuille de projets

Programmation pluriannuelle → Programmation annuelle → PAP → Budget → Exécution
                              ↕                        ↕
                         Alignements              Pont RBM (Piliers / Activités)
```

> **Important :** les **programmes stratégiques** (`/strategie/programmes`) sont distincts des **programmes institutionnels** du cadre organique (`/programmes`).

---

## 2. Accès au module

### Menu principal

1. Connectez-vous à l’application.
2. Dans le menu latéral, section **Planification et PAP**, ouvrez le groupe **Planification stratégique**.
3. Cliquez sur **Hub stratégique** : vous arrivez sur `/strategie/hub`.

Le groupe du menu latéral donne aussi un accès direct à : Vision, Orientations stratégiques, Plans stratégiques, Programmes, Projets, Portefeuilles de projets, Programmation pluriannuelle, Programmation annuelle, Alignements stratégiques, Cohérence stratégique, Pont RBM et Pont PAP.

### Prérequis

- Disposer de la permission **`strategie.view`** (minimum pour consulter).
- Un **exercice budgétaire** doit exister pour les fonctions liées au PAP et au pilotage par exercice.
- Pour la validation des plans, le circuit **« Validation du plan stratégique »** (code `STRATEGIE`) doit être actif dans le module Workflow.

---

## 3. Droits et rôles

| Permission | Action autorisée |
|------------|------------------|
| `strategie.view` | Consulter le hub, les fiches, les rapports et ce manuel |
| `strategie.create` | Créer visions, plans, orientations, programmes, projets, objectifs, etc. |
| `strategie.update` | Modifier, soumettre un plan, joindre des documents, gérer les ponts RBM/PAP |
| `strategie.delete` | Supprimer des enregistrements (selon les règles de chaque fiche) |
| `strategie.archive` | Archiver / restaurer un enregistrement |
| `strategie.validate` | Valider ou rejeter un plan stratégique soumis |
| `strategie.export` | Exporter (Excel, PDF) et archiver des versions officielles |
| `strategie.admin` | Accès administrateur complet au module |

**Attribution par défaut :**

| Rôle | Droits stratégie |
|------|------------------|
| Administrateur | Tous |
| Secrétaire général, Directeur, Commissaire | view, create, update, export, validate, archive |
| Lecteur | view uniquement |

> La suppression (`strategie.delete`) n’est attribuée par défaut qu’à l’administrateur : pour les cycles terminés, préférez l’**archivage**, qui reste réversible.

---

## 4. Navigation dans le module

Une barre de navigation horizontale est présente sur toutes les pages du module :

| Onglet | Rôle |
|--------|------|
| **Hub** | Tableau de bord synthétique |
| **Aide** | Ce manuel (consultation en ligne, téléchargement PDF ou Word) |
| **Pilotage** | Tableau décisionnel (KPIs, alertes, exécution PAP) |
| **Matrice** | Visualisation de la chaîne d’alignement |
| **Cohérence** | Rapport d’anomalies (objectifs, indicateurs, risques) |
| **Alignements** | Enregistrements d’alignement explicites |
| **Pont RBM** | Liaison Piliers ↔ Orientations / Programmes, Activités ↔ Projets |
| **Pont PAP** | Liaison Programmation annuelle ↔ PAP |
| **Prog. pluri. / Prog. annuelle** | Programmations stratégiques |
| **Visions … Risques** | Référentiel et cadre de résultats |

---

## 5. Fonctions communes à toutes les listes et fiches

### 5.1 Recherche et filtre

En haut de chaque liste (visions, plans, programmes, etc.) :

- **Recherche** : saisissez une référence, un code ou un intitulé ;
- **Statut** : limitez la liste à un statut (brouillon, actif, archivé…) ;
- cliquez sur **Filtrer**.

### 5.2 Archiver et restaurer

Chaque enregistrement (vision, plan, orientation, programme, projet, portefeuille, programmation, objectif, indicateur, risque) peut être :

- **archivé** (icône *Archiver*) : il sort des listes actives mais reste consultable et traçable ;
- **restauré** (icône *Restaurer*) depuis la liste filtrée sur le statut archivé.

Permission requise : `strategie.archive`.

### 5.3 Documents stratégiques (GED)

Sur les fiches **Vision** et **Plan**, la section **Documents stratégiques** permet de joindre les pièces officielles (plan approuvé, annexes, notes techniques, rapports) :

- formats acceptés : PDF, Word, Excel, JPG/PNG ;
- taille maximale : **15 Mo** par fichier ;
- chaque document est **indexé automatiquement dans la GED** ;
- les documents peuvent être téléchargés ou supprimés depuis la fiche (`strategie.update`).

---

## 6. Hub — Vue d’ensemble

**URL :** `/strategie/hub`

Le hub affiche :

- **Indicateurs clés** : visions actives, plans, programmes, projets, taux d’alignement, budgets, programmations, objectifs, indicateurs, risques ouverts ;
- **Cohérence stratégique** : piliers orientés, activités rattachées à un projet ;
- **Programmation** : programmation pluriannuelle active et déclinaisons annuelles ;
- **Actions rapides** : création d’alignement, d’objectif, de programmation ;
- **Listes récentes** : dernières visions et derniers plans stratégiques.

**Boutons d’export du hub** (permission `strategie.export`) : **Matrice**, **Pilotage**, **Matrice Excel**, **Synthèse PDF** et **Archiver (officiel)** — voir § 15.

---

## 7. Référentiel stratégique

### 7.1 Visions

**Menu :** **Visions** (`/strategie/visions`)

Une vision institutionnelle (ex. *Vision CEEAC 2035*) définit l’horizon temporel et le cap de la Commission.

**Champs principaux :** référence, intitulé, période, dates, statut, version, description, document officiel.

---

### 7.2 Plans stratégiques

**Menu :** **Plans** (`/strategie/plans`)

Un plan stratégique (ex. *PSC 2025-2030*) est rattaché à une vision et porte les orientations, objectifs et risques. Depuis la fiche plan, les boutons **+ Orientation**, **+ Objectif** et **+ Risque** créent directement les éléments rattachés.

**Statuts :** brouillon, en validation, actif, rejeté, archivé, obsolète.

#### Circuit de validation d’un plan

Le bloc **Circuit de validation** de la fiche plan pilote la démarche :

1. **Brouillon / Rejeté** — le planificateur complète orientations et objectifs.
2. **Soumettre à validation** — bouton visible avec `strategie.update` (le plan doit compter au moins une orientation et un objectif).
3. **En validation** — le plan suit le circuit **« Validation du plan stratégique »** (tableau ci-dessous). Chaque acteur reçoit la tâche dans **Mes tâches** et décide depuis la fiche plan : **Valider le plan**, ou **Rejeter** en saisissant un **motif**.
4. **Actif** — après la dernière approbation : date de validation et validateur sont enregistrés sur la fiche.

| Étape | Libellé | Acteur |
|-------|---------|--------|
| 1 | Validation Direction | Directeur |
| 2 | Approbation Secrétaire Général | Secrétaire général |

En cas de rejet, le plan repasse en **Rejeté** avec le motif affiché ; il peut être corrigé puis soumis à nouveau.

> Si le circuit `STRATEGIE` n’est pas actif dans le module Workflow, la validation se fait en une seule décision par un utilisateur disposant de `strategie.validate`.

---

### 7.3 Orientations stratégiques

**Menu :** **Orientations** (`/strategie/orientations`)

Découpage thématique du plan (ex. intégration économique, paix et sécurité). Chaque orientation est ordonnée et peut porter des objectifs et des programmes.

---

### 7.4 Programmes stratégiques

**Menu :** **Programmes** (`/strategie/programmes`)

Programme d’action rattaché à une orientation. Peut être lié à un **Pilier RBM** ; la liaison met à jour le pont RBM automatiquement.

**Champs utiles :** budget prévisionnel, objectifs, résultats attendus, responsable, dates.

---

### 7.5 Projets stratégiques

**Menu :** **Projets** (`/strategie/projets`)

Projets phares rattachés à un programme. Cycle de vie : planifié, en cours, achevé, suspendu.

Les **activités RBM** sont rattachées à un projet via le **Pont RBM**.

---

### 7.6 Portefeuilles de projets

**Menu :** **Portefeuilles** (`/strategie/portefeuilles`)

Regroupement priorisé de projets pour un exercice ou une période (priorité, catégorie, source de financement).

---

## 8. Cadre de résultats (objectifs, indicateurs, risques)

### 8.1 Objectifs stratégiques

**Menu :** **Objectifs** (`/strategie/objectifs`)

Objectifs mesurables rattachés à un plan (et optionnellement à une orientation). Chaque objectif peut porter plusieurs indicateurs.

---

### 8.2 Indicateurs stratégiques

**Menu :** **Indicateurs** (`/strategie/indicateurs`)

Indicateurs de performance stratégique : unité, périodicité, valeur de référence, **cible**, seuil d’alerte.

**Lien RBM :** un indicateur stratégique peut être lié à un indicateur opérationnel du référentiel RBM pour assurer la continuité du suivi.

---

### 8.3 Risques stratégiques

**Menu :** **Risques** (`/strategie/risques`)

Identification et suivi des risques au niveau plan (et optionnellement projet) : probabilité, impact, mesures de **mitigation**, responsable.

---

## 9. Programmation pluriannuelle et annuelle

### 9.1 Programmation pluriannuelle

**Menu :** **Prog. pluri.** (`/strategie/programmations-pluri`)

Programmation glissante sur plusieurs années, rattachée à un plan stratégique. Peut inclure des scénarios (optimiste, réaliste, pessimiste).

---

### 9.2 Programmation annuelle

**Menu :** **Prog. annuelle** (`/strategie/programmations-annuelles`)

Déclinaison par **exercice** d’une programmation pluriannuelle :

- activités prévues ;
- ressources prévues ;
- indicateurs prévus ;
- **lien PAP** (automatique si un PAP existe pour l’exercice).

**Colonne « PAP lié »** dans la liste : indique si la programmation est rattachée au Plan Annuel de Performance.

Sur la **fiche détail**, un bloc **Réconciliation PAP** compare la programmation à l’exécution opérationnelle.

---

## 10. Alignement et matrice

### 10.1 Matrice d’alignement

**URL :** `/strategie/matrice`

Tableau croisé :

**Vision → Orientation → Programme → Projet → Activité RBM → Pilier**

Chaque ligne indique si l’élément est **aligné** (enregistrement explicite ou rattachement projet/activité).

**Exports :** Excel et PDF paysage.

---

### 10.2 Alignements stratégiques

**URL :** `/strategie/alignements`

Enregistrements formels d’alignement entre la vision, le plan, une orientation et une entité (programme, projet, etc.).

**Création :** bouton **+ Alignement** — choisir le niveau d’alignement (direct, indirect, etc.) et saisir un commentaire. Un alignement erroné se supprime depuis la liste.

---

## 11. Pont RBM

**URL :** `/strategie/pont-rbm`

Interface de liaison entre la planification **stratégique** et le référentiel **RBM/GAR** :

| Liaison | Effet |
|---------|--------|
| Pilier → Orientation | Le pilier RBM est rattaché à l’orientation stratégique |
| Programme → Pilier | Le programme stratégique est rattaché au pilier |
| Activité → Projet | L’activité PAP est rattachée au projet stratégique |

**Bonnes pratiques :**

- Lier d’abord les **piliers** aux orientations avant la clôture du PAP.
- Vérifier la matrice après chaque campagne de liaison.

---

## 12. Pont PAP

**URL :** `/strategie/pont-pap`

Assure la continuité **Programmation annuelle ↔ PAP** par exercice.

**Fonctions :**

- visualiser les programmations et leur statut de liaison ;
- **Synchroniser liaisons PAP** — rattache toutes les programmations de l’exercice au PAP correspondant ;
- accès direct au PAP depuis une programmation liée.

> Si aucun PAP n’existe pour l’exercice, créez-le d’abord dans **Plan annuel de performance → Élaboration et workflow PAP** (`/paps`).

---

## 13. Rapport de cohérence

**URL :** `/strategie/rapport/coherence`

Analyse automatique des **écarts et anomalies** :

| Contrôle | Description |
|----------|-------------|
| Objectifs sans indicateur | Objectifs non déclinés en indicateurs |
| Indicateurs sans cible | Indicateurs incomplets |
| Risques sans mitigation | Risques non couverts |
| Programmes sans budget | Programmes sans enveloppe prévisionnelle |
| Plans en validation | Plans en attente d’approbation |

**Score global** : moyenne des taux de conformité (filtre par plan possible).

**Export PDF** disponible.

---

## 14. Tableau de pilotage décisionnel

**URL :** `/strategie/dashboard/decisionnel`

Vue **décisionnelle** pour la direction :

- **Score pilotage** (synthèse multi-critères) ;
- cohérence, alignement matrice, liaison programmation ↔ PAP ;
- **exécution PAP** de l’exercice (taux physique, financier, budget) ;
- chaîne stratégique → opérationnelle (piliers orientés, activités → projets) ;
- **alertes** automatiques ;
- top 5 programmes et projets par budget.

**Filtre exercice** en haut de page. **Export PDF** paysage.

---

## 15. Exports et archivage officiel

### 15.1 Exports de travail

| Export | Où | Format |
|--------|----|--------|
| Catalogues (visions, plans, orientations, programmes, projets, portefeuilles, objectifs, indicateurs, risques, matrice) | Listes → bouton **Export** | Excel `.xlsx` |
| Synthèse institutionnelle | Hub → **Synthèse PDF** | PDF |
| Matrice d’alignement | Hub → **Matrice Excel** ; Matrice → export | Excel / PDF paysage |
| Rapport de cohérence | Cohérence → export | PDF |
| Tableau de pilotage | Pilotage → export | PDF paysage |

### 15.2 Archiver une version officielle

Le menu **Archiver (officiel)** du hub produit une **version officielle traçable** d’un document, destinée aux instances de gouvernance :

- **Rapports PDF** : Synthèse stratégique, Matrice d’alignement, Rapport de cohérence ;
- **Catalogues Excel** : Visions, Plans, Orientations, Programmes, Projets, Portefeuilles, Matrice, Objectifs, Indicateurs, Risques.

Chaque archivage reçoit une **référence** et un **numéro de version**, une **empreinte** d’intégrité et un **QR code** de vérification. Il est classé en **GED** et retrouvé dans **Reporting → Centre d’exports**.

> Exports et archivage nécessitent la permission **`strategie.export`**.

---

## 16. Parcours utilisateur recommandés

### 16.1 Initialiser un nouveau cycle stratégique

1. Créer ou mettre à jour la **Vision**.
2. Créer le **Plan stratégique** (statut brouillon) et joindre le document de référence.
3. Saisir les **Orientations** et **Objectifs**.
4. Définir **Indicateurs** et **Risques**.
5. Créer les **Programmes** et **Projets**.
6. Constituer le **Portefeuille** de projets.
7. Lancer la **Programmation pluriannuelle** puis les déclinaisons **annuelles**.
8. **Soumettre** le plan à validation → **Validation Direction** → **Approbation SG**.
9. Lier les **Piliers RBM** et les **activités** aux projets (Pont RBM).
10. **Synchroniser** les liaisons PAP (Pont PAP).
11. Contrôler la **Matrice** et le **Rapport de cohérence**.
12. **Archiver (officiel)** la synthèse et la matrice du cycle validé.

### 16.2 Suivi annuel (exercice en cours)

1. Ouvrir le **Tableau de pilotage** (exercice courant).
2. Consulter l’**exécution PAP** et les alertes.
3. Mettre à jour les **risques** et indicateurs si nécessaire.
4. Exporter la **synthèse PDF** pour les instances de gouvernance.

### 16.3 Préparer une réunion de direction

1. **Hub** — chiffres clés en un coup d’œil.
2. **Pilotage** — score et alertes par exercice.
3. **Matrice** — vérifier les trous d’alignement.
4. **Cohérence** — liste des actions correctives.
5. **Archiver (officiel)** le PDF de pilotage et la matrice présentés en séance.

---

## 17. Bonnes pratiques institutionnelles

1. **Une vision, des plans versionnés** — ne pas supprimer ; archiver les cycles passés.
2. **Objectifs SMART** — chaque objectif doit avoir au moins un indicateur avec une cible.
3. **Risques traités** — renseigner systématiquement la mitigation et le responsable.
4. **Alignement explicite** — ne pas se limiter aux liens implicites ; enregistrer les alignements formels.
5. **Pont RBM avant clôture PAP** — garantir que chaque pilier est orienté stratégiquement.
6. **Pont PAP systématique** — synchroniser après création du PAP annuel.
7. **Validation formelle des plans** — respecter le circuit Direction → Secrétariat général.
8. **Documents officiels en GED** — joindre le PSC approuvé sur la fiche plan ou vision.
9. **Versions officielles archivées** — tout document présenté en Conseil ou Conférence passe par **Archiver (officiel)**.
10. **Contrôle de cohérence régulier** — avant Conseil, Conférence ou session budgétaire.

---

## 18. Liens avec les autres modules

| Module | Lien avec la planification stratégique |
|--------|----------------------------------------|
| **RBM/GAR** | Piliers, axes, activités ; indicateurs opérationnels ; matrice RBM |
| **PAP** | Exécution annuelle ; liaison via programmation annuelle et piliers |
| **Budget** | Lignes budgétaires des activités ; budget prévisionnel programmes/projets |
| **Workflow / Mes tâches** | Circuit de validation des plans ; tâches des valideurs |
| **Reporting** | Centre d’exports (versions officielles archivées) ; rapports PAP et RBM |
| **GED** | Documents stratégiques et versions officielles indexés automatiquement |

---

## 19. Dépannage / FAQ

**Je ne vois pas le groupe « Planification stratégique » dans le menu.**  
→ Votre compte n’a pas la permission `strategie.view`. Contactez l’administrateur. Si aucun rôle autre qu’Administrateur n’y a accès, les permissions du module ne sont pas installées : l’administrateur technique doit exécuter le seeder des permissions stratégie.

**Le bouton « Soumettre à validation » n’apparaît pas.**  
→ Le plan doit être en statut *brouillon* ou *rejeté*, et vous devez disposer de `strategie.update`. La soumission exige au moins une orientation et un objectif.

**Mon plan reste « En validation ».**  
→ Il attend la décision de l’acteur de l’étape en cours (Directeur, puis Secrétaire général). Celui-ci retrouve la tâche dans **Mes tâches**.

**Le bouton « Valider le plan » ne fait rien ou renvoie une erreur.**  
→ Seul l’acteur de l’étape en cours peut décider ; vérifiez aussi que vous disposez de `strategie.validate`.

**La programmation annuelle n’est pas liée au PAP.**  
→ Vérifiez qu’un PAP existe pour l’exercice (`/paps`). Utilisez **Pont PAP → Synchroniser liaisons PAP**.

**Le taux d’alignement matrice est faible.**  
→ Complétez le **Pont RBM** (piliers, activités → projets) et enregistrez des **alignements** explicites.

**Où retrouver une version archivée officiellement ?**  
→ **Reporting → Centre d’exports**, ou dans la GED.

**Un enregistrement a disparu de la liste.**  
→ Il a probablement été archivé : filtrez la liste sur le statut *archivé* puis **Restaurer**.

**Export PDF vide ou erreur.**  
→ Vérifiez la permission `strategie.export` et la présence de données pour l’exercice filtré.

**Différence programme stratégique / programme institutionnel ?**  
→ *Programme stratégique* = planification CEEAC (`/strategie/programmes`). *Programme institutionnel* = organigramme interne (`/programmes`).

---

## 20. Référence rapide des URLs

| Page | URL |
|------|-----|
| Hub | `/strategie/hub` |
| Aide (ce manuel) | `/strategie/aide` |
| Pilotage décisionnel | `/strategie/dashboard/decisionnel` |
| Matrice | `/strategie/matrice` |
| Cohérence | `/strategie/rapport/coherence` |
| Alignements | `/strategie/alignements` |
| Pont RBM | `/strategie/pont-rbm` |
| Pont PAP | `/strategie/pont-pap` |
| Visions | `/strategie/visions` |
| Plans | `/strategie/plans` |
| Orientations | `/strategie/orientations` |
| Programmes | `/strategie/programmes` |
| Projets | `/strategie/projets` |
| Portefeuilles | `/strategie/portefeuilles` |
| Objectifs | `/strategie/objectifs` |
| Indicateurs | `/strategie/indicateurs` |
| Risques | `/strategie/risques` |
| Programmations | `/strategie/programmations-pluri` et `/strategie/programmations-annuelles` |

---

*Version du 26 octobre 2026 — module Planification stratégique (phases P0 à P4, circuit de validation workflow, archivage officiel). Pour toute évolution fonctionnelle, référez-vous aux notes de version ou à l’équipe SI.*
