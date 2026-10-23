# Manuel utilisateur — Planification stratégique

**Application :** BUDGET-PAP-SUIVI-CEEAC (CEEAC)  
**Module :** Planification stratégique institutionnelle  
**Public :** Secrétariat général, directions, commissaires, planificateurs, contrôleurs de gestion

---

## 1. Objectif du module

Le module **Planification stratégique** constitue le **niveau supérieur de pilotage** de la performance de la Commission de la CEEAC. Il permet de :

- formaliser la **Vision** et les **Plans stratégiques communautaires** ;
- décliner les **orientations**, **programmes** et **projets** stratégiques ;
- gérer le **portefeuille de projets** et la **programmation pluriannuelle / annuelle** ;
- définir **objectifs**, **indicateurs** et **risques** stratégiques ;
- assurer l’**alignement** avec le référentiel RBM/GAR et le **PAP** ;
- produire des **rapports de cohérence** et un **tableau de pilotage décisionnel**.

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
2. Dans la section **Planification**, cliquez sur **Planification stratégique**.
3. Vous arrivez sur le **Hub** : `/strategie/hub`.

### Prérequis

- Disposer de la permission **`strategie.view`** (minimum pour consulter).
- Un **exercice budgétaire** doit exister pour les fonctions liées au PAP et au pilotage par exercice.

---

## 3. Droits et rôles

| Permission | Action autorisée |
|------------|------------------|
| `strategie.view` | Consulter le hub, les fiches et les rapports |
| `strategie.create` | Créer visions, plans, orientations, programmes, projets, objectifs, etc. |
| `strategie.update` | Modifier, lier des documents, synchroniser les ponts RBM/PAP |
| `strategie.delete` | Supprimer des enregistrements (selon policy) |
| `strategie.archive` | Archiver / restaurer |
| `strategie.validate` | Valider ou rejeter un plan stratégique soumis |
| `strategie.export` | Exporter Excel et PDF |
| `strategie.admin` | Accès administrateur complet |

**Attribution typique :**

| Rôle | Droits stratégie |
|------|------------------|
| Administrateur | Tous |
| Secrétaire général, Directeur, Commissaire | view, create, update, export, validate, archive |
| Lecteur | view uniquement |

---

## 4. Navigation dans le module

Une barre de navigation horizontale est présente sur toutes les pages du module :

| Onglet | Rôle |
|--------|------|
| **Hub** | Tableau de bord synthétique |
| **Pilotage** | Tableau décisionnel (KPIs, alertes, exécution PAP) |
| **Matrice** | Visualisation de la chaîne d’alignement |
| **Cohérence** | Rapport d’anomalies (objectifs, indicateurs, risques) |
| **Alignements** | Enregistrements d’alignement explicites |
| **Pont RBM** | Liaison Piliers ↔ Orientations / Programmes |
| **Pont PAP** | Liaison Programmation annuelle ↔ PAP |
| **Prog. pluri. / annuelle** | Programmations stratégiques |
| **Visions … Risques** | Référentiel et cadre de résultats |

---

## 5. Hub — Vue d’ensemble

**URL :** `/strategie/hub`

Le hub affiche :

- **Indicateurs clés** : visions actives, plans, programmes, projets, taux d’alignement, budgets, programmations, objectifs, indicateurs, risques ouverts ;
- **Cohérence stratégique** : piliers orientés, activités rattachées à un projet ;
- **Programmation** : programmation pluriannuelle active et déclinaisons annuelles ;
- **Actions rapides** : création d’alignement, objectif, programmation ;
- **Listes récentes** : dernières visions et plans stratégiques.

**Exports depuis le hub :**

- Matrice Excel
- Synthèse PDF institutionnelle

---

## 6. Référentiel stratégique

### 6.1 Visions

**Menu :** Orientations → **Visions** (`/strategie/visions`)

Une vision institutionnelle (ex. *Vision CEEAC 2035*) définit l’horizon temporel et le cap de la Commission.

**Champs principaux :** référence, intitulé, période, dates, statut, version, description, document officiel.

**Documents :** sur la fiche vision, section **Documents stratégiques** — téléversement de PDF, notes techniques, rapports (permission `strategie.update`).

---

### 6.2 Plans stratégiques

**Menu :** **Plans** (`/strategie/plans`)

Un plan stratégique (ex. *PSC 2025-2030*) est rattaché à une vision et porte les orientations, objectifs et risques.

**Statuts :** brouillon, en validation, actif, rejeté, archivé, obsolète.

#### Circuit de validation d’un plan

1. **Brouillon / Rejeté** — le planificateur complète orientations et objectifs.
2. **Soumettre à validation** — bouton sur la fiche plan (nécessite au moins une orientation et un objectif).
3. **En validation** — un valideur (`strategie.validate`) approuve ou rejette avec motif.
4. **Actif** — plan validé, date et validateur enregistrés.

**Documents GED :** même mécanisme que pour les visions (plans officiels, annexes).

---

### 6.3 Orientations stratégiques

**Menu :** **Orientations** (`/strategie/orientations`)

Découpage thématique du plan (ex. intégration économique, paix et sécurité). Chaque orientation est ordonnée et peut porter des objectifs et des programmes.

---

### 6.4 Programmes stratégiques

**Menu :** **Programmes** (`/strategie/programmes`)

Programme d’action rattaché à une orientation. Peut être lié à un **Pilier RBM** ; la liaison met à jour le pont RBM automatiquement.

**Champs utiles :** budget prévisionnel, objectifs, résultats attendus, responsable, dates.

---

### 6.5 Projets stratégiques

**Menu :** **Projets** (`/strategie/projets`)

Projets phares rattachés à un programme. Cycle de vie : planifié, en cours, achevé, suspendu.

Les **activités RBM** peuvent être rattachées à un projet via le **Pont RBM** ou le champ `projet_id` sur les activités.

---

### 6.6 Portefeuilles de projets

**Menu :** **Portefeuilles** (`/strategie/portefeuilles`)

Regroupement priorisé de projets pour un exercice ou une période (priorité, catégorie, source de financement).

---

## 7. Cadre de résultats (objectifs, indicateurs, risques)

### 7.1 Objectifs stratégiques

**Menu :** **Objectifs** (`/strategie/objectifs`)

Objectifs mesurables rattachés à un plan (et optionnellement à une orientation). Chaque objectif peut porter plusieurs indicateurs.

---

### 7.2 Indicateurs stratégiques

**Menu :** **Indicateurs** (`/strategie/indicateurs`)

Indicateurs de performance stratégique : unité, périodicité, valeur de référence, **cible**, seuil d’alerte.

**Pont RBM :** un indicateur stratégique peut être lié à un indicateur opérationnel du référentiel RBM pour assurer la continuité du suivi.

---

### 7.3 Risques stratégiques

**Menu :** **Risques** (`/strategie/risques`)

Identification et suivi des risques au niveau plan (et optionnellement projet) : probabilité, impact, mesures de **mitigation**, responsable.

---

## 8. Programmation pluriannuelle et annuelle

### 8.1 Programmation pluriannuelle

**Menu :** **Prog. pluri.** (`/strategie/programmations-pluri`)

Programmation glissante sur plusieurs années, rattachée à un plan stratégique. Peut inclure des scénarios (optimiste, réaliste, pessimiste).

---

### 8.2 Programmation annuelle

**Menu :** **Prog. annuelle** (`/strategie/programmations-annuelles`)

Déclinaison par **exercice** d’une programmation pluriannuelle :

- activités prévues ;
- ressources prévues ;
- indicateurs prévus ;
- **lien PAP** (automatique si un PAP existe pour l’exercice).

**Colonne « PAP lié »** dans la liste : indique si la programmation est rattachée au Plan Annuel de Performance.

Sur la **fiche détail**, un bloc **Réconciliation PAP** compare la programmation à l’exécution opérationnelle.

---

## 9. Alignement et matrice

### 9.1 Matrice d’alignement

**URL :** `/strategie/matrice`

Tableau croisé :

**Vision → Orientation → Programme → Projet → Activité RBM → Pilier**

Chaque ligne indique si l’élément est **aligné** (enregistrement explicite ou rattachement projet/activité).

**Exports :** Excel et PDF paysage.

---

### 9.2 Alignements stratégiques

**URL :** `/strategie/alignements`

Enregistrements formels d’alignement (polymorphiques) entre la vision, le plan, une orientation et une entité (programme, projet, etc.).

**Création :** bouton « + Alignement » — choisir le niveau d’alignement (direct, indirect, etc.) et un commentaire.

---

## 10. Pont RBM

**URL :** `/strategie/pont-rbm`

Interface de liaison entre la planification **stratégique** et le référentiel **RBM/GAR** :

| Liaison | Effet |
|---------|--------|
| Pilier → Orientation | Le pilier RBM hérite de l’orientation stratégique |
| Programme → Pilier | Le programme stratégique est rattaché au pilier |
| Activité → Projet | L’activité PAP est rattachée au projet stratégique |

**Bonnes pratiques :**

- Lier d’abord les **piliers** aux orientations avant la clôture du PAP.
- Vérifier la matrice après chaque campagne de liaison.

---

## 11. Pont PAP

**URL :** `/strategie/pont-pap`

Assure la continuité **Programmation annuelle ↔ PAP** par exercice.

**Fonctions :**

- visualiser les programmations et leur statut de liaison ;
- **Synchroniser liaisons PAP** — rattache automatiquement toutes les programmations de l’exercice au PAP correspondant ;
- accès direct au PAP depuis une programmation liée.

> Si aucun PAP n’existe pour l’exercice, créez-le d’abord dans **Planification → PAP** (`/paps`).

---

## 12. Rapport de cohérence

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

## 13. Tableau de pilotage décisionnel

**URL :** `/strategie/dashboard/decisionnel`

Vue **decisionnelle** pour la direction :

- **Score pilotage** (synthèse multi-critères) ;
- cohérence, alignement matrice, liaison programmation ↔ PAP ;
- **exécution PAP** de l’exercice (taux physique, financier, budget) ;
- chaîne stratégique → opérationnelle (piliers orientés, activités → projets) ;
- **alertes** automatiques ;
- top 5 programmes et projets par budget.

**Filtre exercice** en haut de page. **Export PDF** paysage.

---

## 14. Exports

| Export | URL / action | Format |
|--------|--------------|--------|
| Catalogues (visions, plans, orientations, programmes, projets, portefeuilles, objectifs, indicateurs, risques, matrice) | Hub ou listes → bouton **Export** | Excel `.xlsx` |
| Synthèse institutionnelle | Hub → **Synthèse PDF** | PDF |
| Matrice d’alignement | Matrice → export | PDF paysage / Excel |
| Rapport cohérence | Cohérence → export | PDF |
| Tableau pilotage | Pilotage → export | PDF paysage |

> Nécessite la permission **`strategie.export`**.

---

## 15. Parcours utilisateur recommandés

### 15.1 Initialiser un nouveau cycle stratégique

1. Créer ou mettre à jour la **Vision**.
2. Créer le **Plan stratégique** (statut brouillon).
3. Saisir les **Orientations** et **Objectifs**.
4. Définir **Indicateurs** et **Risques**.
5. Créer les **Programmes** et **Projets**.
6. Constituer le **Portefeuille** de projets.
7. Lancer la **Programmation pluriannuelle** puis les déclinaisons **annuelles**.
8. **Soumettre** le plan à validation → **Valider** (SG / Commissaire).
9. Lier les **Piliers RBM** (Pont RBM) et les **activités** aux projets.
10. **Synchroniser** les liaisons PAP.
11. Contrôler la **Matrice** et le **Rapport de cohérence**.

### 15.2 Suivi annuel (exercice en cours)

1. Ouvrir le **Tableau de pilotage** (exercice courant).
2. Consulter l’**exécution PAP** et les alertes.
3. Mettre à jour les **risques** et indicateurs si nécessaire.
4. Exporter la **synthèse PDF** pour les instances de gouvernance.

### 15.3 Préparer une réunion de direction

1. **Hub** — chiffres clés en un coup d’œil.
2. **Pilotage** — score et alertes par exercice.
3. **Matrice** — vérifier les trous d’alignement.
4. **Cohérence** — liste des actions correctives.
5. Exporter **PDF pilotage** + **matrice**.

---

## 16. Bonnes pratiques institutionnelles

1. **Une vision, des plans versionnés** — ne pas supprimer ; archiver les cycles passés.
2. **Objectifs SMART** — chaque objectif doit avoir au moins un indicateur avec une cible.
3. **Risques traités** — renseigner systématiquement la mitigation et le responsable.
4. **Alignement explicite** — ne pas se limiter aux liens implicites ; enregistrer les alignements formels.
5. **Pont RBM avant clôture PAP** — garantir que chaque pilier est orienté stratégiquement.
6. **Pont PAP systématique** — synchroniser après création du PAP annuel.
7. **Validation formelle des plans** — respecter le circuit brouillon → validation → actif.
8. **Documents officiels en GED** — joindre le PSC approuvé sur la fiche plan ou vision.
9. **Contrôle de cohérence régulier** — avant Conseil, Conférence ou session budgétaire.

---

## 17. Liens avec les autres modules

| Module | Lien avec la planification stratégique |
|--------|----------------------------------------|
| **RBM/GAR** | Piliers, axes, activités ; indicateurs opérationnels ; matrice RBM |
| **PAP** | Exécution annuelle ; liaison via programmation annuelle et piliers |
| **Budget** | Lignes budgétaires des activités ; budget prévisionnel programmes/projets |
| **Reporting** | Rapports PAP et RBM alimentés par la chaîne alignée |
| **GED** | Documents stratégiques (plans, visions) ; pièces PAP/Budget séparées |

---

## 18. Dépannage / FAQ

**Je ne vois pas le menu « Planification stratégique ».**  
→ Votre compte n’a pas la permission `strategie.view`. Contactez l’administrateur.

**Le bouton « Soumettre à validation » n’apparaît pas.**  
→ Le plan doit être en statut *brouillon* ou *rejeté*, avec au moins une orientation et un objectif.

**La programmation annuelle n’est pas liée au PAP.**  
→ Vérifiez qu’un PAP existe pour l’exercice (`/paps`). Utilisez **Pont PAP → Synchroniser liaisons PAP**.

**Le taux d’alignement matrice est faible.**  
→ Complétez le **Pont RBM** (piliers, activités → projets) et enregistrez des **alignements** explicites.

**Export PDF vide ou erreur.**  
→ Vérifiez la permission `strategie.export` et la présence de données pour l’exercice filtré.

**Différence programme stratégique / programme institutionnel ?**  
→ *Programme stratégique* = planification CEEAC (`/strategie/programmes`). *Programme institutionnel* = organigramme interne (`/programmes`).

---

## 19. Référence rapide des URLs

| Page | URL |
|------|-----|
| Hub | `/strategie/hub` |
| Pilotage décisionnel | `/strategie/dashboard/decisionnel` |
| Matrice | `/strategie/matrice` |
| Cohérence | `/strategie/rapport/coherence` |
| Alignements | `/strategie/alignements` |
| Pont RBM | `/strategie/pont-rbm` |
| Pont PAP | `/strategie/pont-pap` |
| Visions | `/strategie/visions` |
| Plans | `/strategie/plans` |
| Objectifs | `/strategie/objectifs` |
| Indicateurs | `/strategie/indicateurs` |
| Risques | `/strategie/risques` |
| Programmations | `/strategie/programmations-pluri` et `/strategie/programmations-annuelles` |

---

*Document généré pour la version du module Planification stratégique — phases P0 à P4. Pour toute évolution fonctionnelle, référez-vous aux notes de version ou à l’équipe SI.*
