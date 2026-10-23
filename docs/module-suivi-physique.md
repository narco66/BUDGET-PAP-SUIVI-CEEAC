# Manuel utilisateur — Suivi physique des réalisations

**Application :** BUDGET-PAP-SUIVI-CEEAC (CEEAC)  
**Module :** Suivi-évaluation / Suivi physique (phases D7, S1–S4)  
**Public :** Points focaux, chefs de service, commissaires, DPPB

---

## 1. Objectif

Mesurer et documenter l'**avancement physique** des activités PAP, indépendamment de l'exécution financière, avec comparaison phys ↔ fin et consolidation par produit.

```text
PAP publié → CampagneSuivi → Collecte (par activité) → Validation → Report sur activité
```

---

## 2. Accès

| Fonction | Route |
|----------|-------|
| Campagnes | `/suivi` |
| Saisie grille | `/suivi/{id}` |
| Exports collectes | `/suivi/{id}/export/collectes/{pdf\|xlsx\|csv}` |

**Menu :** Suivi-évaluation (permission `suivi.voir` ou `suivi_physique.view`)

---

## 3. Permissions

| Permission audit | Alias application |
|------------------|-------------------|
| `suivi_physique.view` | `suivi.voir` |
| `suivi_physique.create` | `suivi.creer` |
| `suivi_physique.update` | `suivi.modifier` |
| `suivi_physique.validate` | `suivi.valider` |
| `suivi_physique.export` | `suivi.exporter` |
| `suivi_physique.admin` | `suivi.valider` (+ administrateur) |

---

## 4. Campagnes de suivi

Types : **mensuel**, trimestriel, semestriel, annuel, pluriannuel.

1. Créer une campagne (exercice, période `2026-T1` ou `2026-M01`).
2. Saisir les collectes par activité PAP.
3. Vérifier puis **valider** (séparation saisie / validation ; justificatif obligatoire si activé).
4. **Clôturer** la campagne → consolidation sous-produits + produits + alertes persistées.

> Le PAP de l'exercice doit être **publié** avant toute saisie.

---

## 5. Saisie d'une collecte

Par activité, renseigner :

- **Taux physique (%)** *ou* **quantité prévue / réalisée** (calcul automatique du taux) ;
- Montant réalisé (déclaratif trimestriel) ;
- Cause d'écart (si écart phys/fin ≥ 15 pts) ;
- Commentaire.

Workflow : `saisie → verifiée → validée` → report sur `activites.taux_realisation_physique`.

---

## 6. Taux et écarts

| Indicateur | Formule |
|------------|---------|
| Taux physique (quantités) | `(quantité réalisée / quantité prévue) × 100` |
| Taux financier réel | `montant payé (ligne budgétaire) / budget activité × 100` |
| Écart | `taux physique − taux financier réel` |

Roll-up **tâches → activité** : moyenne des `taux_realisation` des tâches (`SuiviPhysiqueService`). À la validation, si `SUIVI_SYNC_TACHES_AUTO=true`, le taux issu des tâches prime sur le taux déclaré dans la collecte. À la saisie, si aucun taux ni quantité n'est renseigné, le taux est pré-rempli depuis les tâches.

---

## 7. Consolidation sous-produits (phase S3)

À la **clôture** de campagne, snapshot dans `realisation_sous_produits` :

- taux physique / financier par sous-produit ;
- nombre d'activités et collectes validées.

Affiché sur la fiche campagne après clôture, au-dessus de la consolidation produits.

---

## 8. Consolidation produits (phase S4)

À la **clôture** de campagne, snapshot dans `realisation_produits` :

- taux physique / financier par produit ;
- nombre d'activités et collectes validées.

Alertes persistées dans `alertes_performance` (sous-performance physique < 50 %, écarts phys/fin ≥ 15 pts sans cause). Affichées sur la fiche campagne après clôture.

---

## 9. Justificatifs (GED — phase S1)

- **Campagne** : documents GED rattachés à la campagne.
- **Collecte** : widget par ligne d'activité (GED + pièces jointes).

Configuration : `SUIVI_JUSTIFICATIF_OBLIGATOIRE=true` (défaut) — au moins un justificatif requis avant validation.

Types acceptés : PDF, images, Office, CSV.

---

## 10. Exports (phase S2)

| Format | Contenu |
|--------|---------|
| Excel / CSV | Grille collectes (taux, quantités, écarts, statuts) |
| PDF | Rapport campagne |

Route : `suivi.export.collectes` — permission `suivi.exporter` ou `suivi_physique.export` (policy `export` sur campagne).

---

## 11. Audit automatisé

```bash
php artisan suivi:audit --exercice=2026
```

Options : `--json`, `--persister`, `--seuil=elevee`, `--detail`

Sections : amont, campagnes, collectes, justificatifs, consolidation, tâches.

Score de conformité /100 ; snapshot dans `audit_suivi_physique` avec `--persister`.

---

## 12. Seeder démo

```bash
php artisan db:seed --class=SuiviPhysiqueSeeder
```

Charge permissions S4 + campagne T1 2026 avec collectes démo.

---

## 13. Intégrations

| Module | Lien |
|--------|------|
| PAP / RBM | Activités, tâches, produits |
| Budget | Taux financier réel via lignes budgétaires |
| Reporting | Activités en retard, cockpit |
| GED | Justificatifs campagne / collecte |

---

*Document phases S1–S4 — GED/justificatifs, policies/exports, roll-up tâches, consolidation sous-produits/produits, audit automatisé.*
