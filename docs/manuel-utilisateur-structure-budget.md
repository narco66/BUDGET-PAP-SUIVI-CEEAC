# Manuel utilisateur — Structure du Budget

**Application :** BUDGET-PAP-SUIVI-CEEAC (CEEAC)  
**Module :** Structure du Budget — restitution, saisie et validation des lignes  
**Public :** DPPB, gestionnaires budgétaires, directeurs, contrôleurs, auditeurs

---

## 1. Objectif

Le module **Structure du Budget** permet de :

- visualiser l'**organigramme budgété** avec montants consolidés (rollup) ;
- **rechercher** et consulter les lignes budgétaires dans leur contexte (PAP, nomenclature, financement) ;
- **saisir** de nouvelles lignes via un assistant guidé (wizard) ;
- **importer / exporter** la structure consolidée ;
- **valider** les lignes selon un circuit brouillon → soumis → validé / rejeté ;
- consulter le **journal** des opérations et le **tableau de bord** de cohérence.

> Il n'existe pas d'entité unique « structure budgétaire » : le module **agrège** les tables existantes (`structures`, `lignes_budgetaires`, RBM, nomenclature).

---

## 2. Accès

**Menu :** Budgétisation → **Structure du budget** (`/structure-budget`)

| Écran | Route |
|-------|-------|
| Arbre institutionnel | `/structure-budget` |
| Arbre complet (PAP + institutionnel) | `/structure-budget/arbre-complet` |
| Recherche | `/structure-budget/recherche` |
| Tableau de bord | `/structure-budget/tableau-bord` |
| Assistant de saisie | `/structure-budget/wizard` |
| Import / Export | `/structure-budget/import-export` |
| Journal | `/structure-budget/journal` |

---

## 3. Permissions

| Permission | Rôle typique | Action |
|------------|--------------|--------|
| `structure_budget.voir` | Tous (`*.voir`) | Consulter arbre, recherche, tableau |
| `structure_budget.creer` | Gestionnaire budgétaire | Wizard, création de lignes |
| `structure_budget.modifier` | Gestionnaire budgétaire | Édition, soumission, réconciliation |
| `structure_budget.valider` | Directeur | Valider une ligne soumise |
| `structure_budget.rejeter` | Directeur | Rejeter avec motif |
| `structure_budget.exporter` | Gestionnaire | Excel, CSV, PDF |
| `structure_budget.importer` | Gestionnaire | Import avec prévisualisation |
| `structure_budget.consulter_journal` | Directeur, auditeur | Journal des opérations |

---

## 4. Parcours de consultation

### 4.1 Arbre institutionnel

1. Sélectionner l'**exercice** (coin supérieur droit).
2. Déplier les nœuds (Département → Direction → Service).
3. Chaque nœud affiche le **total ouvert consolidé** (propres + descendants).
4. Cliquer sur une structure pour voir ses lignes rattachées.

### 4.2 Recherche et fiche ligne

- Filtrer par code, structure, pilier, rubrique.
- La **fiche ligne** présente : montants, chaîne PAP/RBM, nomenclature, imputations d'engagement.

### 4.3 Tableau de bord

- KPI globaux, répartition par département/direction/service.
- **Anomalies** : liaisons RBM, rubriques non validées, écarts PAP.
- **Évolution N/N-1**, répartition par sens budgétaire.
- Bouton **Réconcilier** si des liaisons activité ↔ ligne sont désynchronisées.

---

## 5. Saisie d'une ligne (wizard)

1. **Structure du budget** → **Assistant de saisie**.
2. Choisir exercice, structure, rubrique (validée), source, activité PAP optionnelle.
3. Saisir code et montant ouvert — le système **vérifie** en temps réel (doublon, rubrique, dépassement PAP).
4. Enregistrer → la ligne est créée en statut **brouillon**.

---

## 6. Circuit de validation

| Statut | Signification | Actions possibles |
|--------|---------------|-------------------|
| **brouillon** | Saisie en cours | Modifier, soumettre |
| **soumis** | En attente directeur | Valider ou rejeter (directeur) |
| **valide** | Approuvé | Consultation seule |
| **rejete** | À corriger | Modifier, resoumettre |

**Soumettre :** fiche ligne → bouton *Soumettre* (gestionnaire).  
**Valider / Rejeter :** fiche ligne ou file d'attente dans le **Journal** (directeur).  
Le rejet exige un **motif** obligatoire.

---

## 7. Import / Export

**Export :** hub Import/Export ou icônes depuis l'arbre — formats **xlsx**, **csv**, **pdf**.  
**Import :** télécharger le modèle → remplir → upload → **prévisualisation** → confirmer.

Colonnes principales : `code`, `intitule`, `structure_code`, `rubrique_code`, `montant_ouvert`, `activite_code` (optionnel).

---

## 8. API REST (intégration)

Authentification Sanctum, scope `lecture` pour la consultation.

```
GET  /api/v1/structure-budget/synthese?exercice=
GET  /api/v1/structure-budget/arbre?exercice=
GET  /api/v1/structure-budget/lignes?exercice=&statut_validation=
POST /api/v1/structure-budget/lignes/{id}/soumettre|valider|rejeter
```

---

## 9. Recette MOA Budget 2026

Avant signature du procès-verbal, exécuter :

```bash
php artisan structure-budget:recette-2026
php artisan test --filter=StructureBudget
```

Référence : `docs/recette-structure-budget-2026.md` et `docs/pv-recette-structure-budget-2026.md`.

---

## 10. Support

| Document | Contenu |
|----------|---------|
| `docs/module-structure-budget.md` | Architecture technique |
| `docs/recette-structure-budget-2026.md` | Scénarios de test MOA |
| `docs/checklist-animateur-PV-REC-SB-2026-01.md` | Checklist jour J |

Contact MOE : équipe projet BUDGET-PAP — DSI CEEAC.
