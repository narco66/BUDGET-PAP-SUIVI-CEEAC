# Manuel utilisateur — Chaîne de la dépense

**Application :** BUDGET-PAP-SUIVI-CEEAC (CEEAC)  
**Module :** Gestion de la chaîne de la dépense (phases D0–D5)  
**Public :** Chefs de service, directeurs, DPPB, gestionnaires budgétaires, ordonnateur (SG), comptables

---

## 1. Objectif du module

Le module **Chaîne de la dépense** couvre le circuit institutionnel complet :

```
Besoin → Engagement → Liquidation → Ordonnancement → Paiement
```

Il garantit :

- la **traçabilité PAP** (activité, tâche, ligne budgétaire) ;
- la **séparation des fonctions** (validation hiérarchique, visa CF, ordonnateur, comptable) ;
- la **cohérence des compteurs** budgétaires (engagé, liquidé, ordonnancé, payé) ;
- le **rapprochement bancaire** et l'**audit automatisé** ;
- les **écritures SYSCOHADA** et l'interface **TomPro** (multi-devises).

---

## 2. Accès

| Fonction | Route / menu |
|----------|--------------|
| Expressions du besoin | `/besoins` |
| Engagements | `/engagements` |
| Liquidations / Ordonnancements / Paiements | `/depense/{type}` |
| Dossier unifié | `/depense/dossier/besoin/{id}` ou `/depense/dossier/engagement/{id}` |
| Consolidation des besoins | `/besoins/consolidation` |
| Rapprochement bancaire | `/depense/rapprochement` |
| Exports | `/depense/export/*` |

---

## 3. Rôles et permissions

| Rôle | Étapes concernées |
|------|-------------------|
| **Chef de service** | Création et 1ʳᵉ validation du besoin |
| **Directeur** | 2ᵉ validation du besoin |
| **Contrôleur financier (DPPB)** | Visa final du besoin, engagement, liquidation |
| **Gestionnaire budgétaire** | Création engagement, liquidations, ordonnancements, paiements |
| **Secrétaire général (ordonnateur)** | Visa ordonnancement |
| **Comptable** | Exécution et visa paiement |

Permissions principales : `besoins.*`, `engagements.*`, `liquidations.*`, `ordonnancements.*`, `paiements.*`, `depenses.admin` (contournement réservé aux administrateurs).

---

## 4. Circuit détaillé

### 4.1 Expression du besoin (3 étapes)

1. **Créer** le besoin : exercice, activité PAP, ligne budgétaire, montant estimé, priorité (optionnel : tâche).
2. **Soumettre** au circuit.
3. Valider successivement : Chef de service → Directeur → Contrôleur financier.
4. Statut final : **validé**.

> Un besoin validé est obligatoire pour créer un engagement (sauf profil `depenses.admin`).

### 4.2 Engagement

1. Depuis un besoin validé : **Convertir en engagement** (pré-remplissage).
2. Saisir les imputations par ligne budgétaire.
3. **Soumettre** → visa du **contrôleur financier**.
4. Statut final : **visé** (crédits réservés sur la ligne).

### 4.3 Liquidation

1. Depuis un engagement visé : créer la liquidation (montant, **constat de service fait**).
2. Soumettre → visa contrôleur financier.
3. Met à jour le compteur **liquidé**.

### 4.4 Ordonnancement

1. Depuis une liquidation validée.
2. Soumettre → visa **ordonnateur** (SG).
3. Met à jour le compteur **ordonnancé**.

### 4.5 Paiement

1. Depuis un ordonnancement validé.
2. Saisir mode de paiement, devise, taux de change (si ≠ XAF), référence bancaire.
3. Soumettre → visa **comptable**.
4. Met à jour le compteur **payé** (en XAF) et génère les **écritures comptables SYSCOHADA**.

---

## 5. Dossier unifié

La vue **Dossier dépense unifié** regroupe sur une timeline :

- le besoin d'origine, l'engagement, les liquidations, ordonnancements et paiements ;
- la tâche PAP et la priorité ;
- les constats de service fait ;
- les montants et statuts de chaque étape.

Accès depuis la fiche engagement ou via le menu dépenses.

---

## 6. Consolidation des besoins

Route : `/besoins/consolidation`

Permet de regrouper plusieurs expressions du besoin validées en vue d'une programmation ou d'un arbitrage interne, avec export des consolidations.

---

## 7. Rapprochement bancaire

Route : `/depense/rapprochement`

1. **Créer** une session de rapprochement (période, compte bancaire).
2. **Importer** le relevé bancaire (CSV).
3. **Rapprocher** automatiquement ou manuellement avec les paiements enregistrés.
4. **Clôturer** la session une fois les écarts traités.

---

## 8. Exports

| Export | Route | Contenu |
|--------|-------|---------|
| Besoins | `/depense/export/besoins` | Journal des expressions du besoin |
| Engagements | `/depense/export/engagements` | Journal des engagements |
| Liquidations | `/depense/export/liquidations` | Journal des liquidations |
| Ordonnancements | `/depense/export/ordonnancements` | Journal des ordonnancements |
| Paiements | `/depense/export/paiements` | Journal des paiements |
| Restes à payer | `/depense/export/restes-a-payer` | Engagements non soldés |
| Journal consolidé | `/depense/export/consolide` | Vue transversale Besoin → Paiement |
| Écritures comptables | `/depense/export/ecritures` | Écritures SYSCOHADA (phase D4) |

Formats : Excel (.xlsx) ou CSV selon l'export.

---

## 9. Audit automatisé

### Commande CLI

```bash
php artisan depense:audit --exercice=2026
```

Options :

| Option | Description |
|--------|-------------|
| `--exercice=` | ID ou année de l'exercice (recommandé) |
| `--seuil=elevee` | Code de sortie ≠ 0 si anomalie ≥ seuil (`elevee`, `moyenne`, `faible`) |
| `--detail` | Afficher toutes les anomalies |
| `--persister` | Enregistrer le snapshot dans `audit_depenses` |
| `--json` | Sortie JSON (CI / intégration) |

### Sections contrôlées

- **amont** : besoin sans ligne, engagement sans besoin/activité ;
- **enchaînement** : liquidation sans engagement visé, ordonnancement sans liquidation validée, etc. ;
- **montants** : dépassements engagement → liquidation → ordonnancement → paiement ;
- **paiements** : références manquantes, rejets non traités ;
- **comptabilite** : paiement validé sans écriture, écritures orphelines ;
- **compteurs** : écarts entre mouvements et soldes des lignes budgétaires.

Le rapport inclut un **score de conformité** (/100) et une synthèse (volumes, montants payés en XAF).

### Bonnes pratiques

- Exécuter l'audit **avant clôture mensuelle** ou **trimestrielle**.
- Viser **score 100** et **0 anomalie de gravité élevée** avant transmission au contrôle interne.
- Utiliser `--persister` pour conserver l'historique des audits.

---

## 10. Multi-devises et TomPro (phase D4)

- Les paiements peuvent être saisis en **devise étrangère** avec taux de change ; le montant **XAF** alimente les compteurs budgétaires.
- Les **écritures SYSCOHADA** sont générées automatiquement à la validation du paiement.
- L'API TomPro (`/api/v1/tompro/paiements`) expose paiements et écritures pour l'interface comptable externe.

---

## 11. Invariants métier

1. **Payé ≤ Ordonnancé ≤ Liquidé ≤ Engagé** sur chaque ligne budgétaire.
2. Chaque engagement (hors admin) est rattaché à un **besoin validé** et une **activité PAP**.
3. Les compteurs ne sont appliqués **qu'une fois** par document (`compteurs_appliques`).
4. Un paiement validé possède au minimum une **écriture comptable** associée.

---

## 12. Dépannage

| Symptôme | Action |
|----------|--------|
| Impossible de créer un engagement | Vérifier que le besoin est **validé** et qu'une ligne budgétaire est renseignée |
| Liquidation refusée | Engagement doit être **visé** ; montant ≤ reste à liquider |
| Compteurs incohérents | Lancer `depense:audit --detail` ; contacter l'administrateur si écart compteur |
| Paiement sans écriture | Re-valider ou contacter le support (anomalie `paiement_sans_ecriture_comptable`) |

---

*Document généré dans le cadre de la phase D5 — audit automatisé, tests E2E et documentation utilisateur.*
