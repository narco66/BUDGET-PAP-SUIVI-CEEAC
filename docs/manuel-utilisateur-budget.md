# Manuel utilisateur — Gestion budgétaire institutionnelle

**Application :** BUDGET-PAP-SUIVI-CEEAC (CEEAC)  
**Module :** Enveloppe budgétaire et cycle institutionnel (phases B0–B5)  
**Public :** DPPB, gestionnaires budgétaires, contrôleurs financiers, directeurs

---

## 1. Objectif du module

Le module **Gestion budgétaire** centralise :

- le **référentiel des crédits** (lignes budgétaires, nomenclature, ventilations PTF) ;
- l'**enveloppe institutionnelle** et son **circuit d'adoption** ;
- la **préparation**, l'**arbitrage** et la **consolidation** des propositions ;
- les **simulations** what-if et les **révisions** (rectificatifs / virements) ;
- le **lien programmation pluriannuelle** et les **rapports de conformité**.

### Chaîne institutionnelle

```
Exercice → Enveloppe (B1) → Préparation / arbitrage (B2) → Consolidation & simulation (B3)
         → Révisions & pluriannuel (B4) → Exécution (engagements → paiements)
```

---

## 2. Accès

**Menu :** Budgétisation → **Budget & Crédits** (`/budget`)

**Enveloppe :** Budgétisation → lien **Circuit d'adoption** ou `/budget/enveloppe`

---

## 3. Permissions

| Permission FR | Alias anglais | Action |
|---------------|---------------|--------|
| `budget.voir` | `budget.view` | Consulter lignes, enveloppe, rapports |
| `budget.creer` | `budget.create` | Créer enveloppe / lignes |
| `budget.modifier` | `budget.update`, `budget.prepare` | Modifier, préparer |
| `budget.valider` | `budget.validate` | Valider dans le circuit |
| `budget.arbitrer` | `budget.arbitrate` | Arbitrage DPPB |
| `budget.consolider` | `budget.consolidate` | Consolidation |
| `budget.simuler` | `budget.simulate` | Scénarios what-if |
| `budget.rectifier` | `budget.amend` | Rectificatifs / virements |
| `budget.exporter` | `budget.export`, `budget.report` | Exports |

---

## 4. Cycle de vie — enveloppe (B1)

1. **Créer** l'enveloppe pour l'exercice (`BUDGET-AAAA`).
2. **Initialiser** les propositions depuis les lignes (B2).
3. Exécuter `budget:audit` — **0 anomalie** requis avant soumission.
4. **Soumettre** au circuit de validation (6 étapes).
5. Après **adoption**, les lignes sont **verrouillées**.

**Révision post-adoption :** bouton « Ouvrir en révision » sur la fiche enveloppe.

---

## 5. Préparation & arbitrage (B2)

Route : `/budget/enveloppe/{id}/preparations`

- Initialiser les propositions par ligne / structure
- Circuit : brouillon → service → direction → département → DPPB → arbitrage → consolidé
- **Consolider** applique les montants sur les lignes budgétaires

---

## 6. Consolidation & simulation (B3)

Route : `/budget/enveloppe/{id}/simulations`

- Historique des **snapshots de consolidation**
- Création de **scénarios** (variation %, plafond, par structure)
- **Comparaison** et scénario **retenu**

---

## 7. Révisions & pluriannuel (B4)

Route : `/budget/enveloppe/{id}/revisions`

- **Rectificatif** : ajuster le montant d'une ligne (`montant_revise` si adopté)
- **Virement** : transfert entre deux lignes
- **Rattachement** à la programmation pluriannuelle PSC

---

## 8. Qualité & rapports (B5)

### Commandes CLI

```bash
php artisan budget:audit --exercice=2026 [--json]
php artisan budget:rapport --exercice=2026 [--json]
php artisan budget:completer-donnees [--dry-run]
```

### Rapport institutionnel

- **Web :** fiche enveloppe → **Rapport** ou `/budget/rapport`
- **Export PDF/Excel :** Reporting → « Rapport budgétaire institutionnel »

Le rapport affiche la **checklist B0–B4**, le score de conformité et les alertes cycle.

---

## 9. Bonnes pratiques

1. Toujours lancer `budget:completer-donnees` puis `budget:audit` avant soumission.
2. Ne pas consolider sans arbitrage préalable des propositions.
3. Documenter chaque rectificatif / virement (motif obligatoire).
4. Rattacher l'enveloppe à la programmation pluriannuelle active.
5. Consulter le **rapport cycle** avant le Conseil des ministres / adoption.

---

*Document généré dans le cadre de la phase B5 — qualité module budget CEEAC.*
