# Compte-rendu de démonstration
## Module Suivi-Évaluation — BUDGET-PAP-SUIVI-CEEAC

**Document type :** Compte-rendu de session de démonstration  
**Version du modèle :** 1.0 — Juillet 2026  
**Application :** BUDGET-PAP-SUIVI-CEEAC  
**Module présenté :** Suivi-Évaluation (P0 → P3)

---

> **Mode d'emploi :** compléter dans les 48 h suivant la démo (deadline **vendredi 05/07/2026**). Copier dans Word (charte CEEAC), joindre en annexe la checklist animateur signée, diffuser à la commission recette et archiver sur **archives-moa@ceeac.int**.

---

| **Référence CR** | CR-DEMO-SE-2026-01 |
|------------------|-------------------|
| **Démo associée** | DEMO-SE-2026-01 |
| **Recette MOA prévue** | PV-REC-SE-2026-01 — **09/07/2026** |
| **Date de la démo** | 03/07/2026 *(jeudi)* |
| **Horaires effectifs** | Début [__h__] — Fin [__h__] |
| **Lieu** | Siège CEEAC — Lab SI — Bâtiment Informatique (DSI) — RDC aile Nord |
| **Exercice présenté** | 2026 |
| **Environnement** | ☑ Préproduction   ☐ Autre : _________ |
| **URL** | https://budget-pap-preprod.ceeac.int |

---

## 1. Objet

Le présent compte-rendu consigne les éléments de la **démonstration préalable** au procès-verbal de recette MOA **PV-REC-SE-2026-01**, tenue le **3 juillet 2026** dans le Lab SI de la DSI.

Il vise à :

- attester de la **tenue effective** de la session DEMO-SE-2026-01 ;
- recenser les **participants** et le **déroulé** ;
- formaliser les **retours MOA** (questions, réserves, demandes d'évolution) ;
- identifier les **points à traiter avant la recette** du 09/07/2026 ;
- orienter la **préparation de la grille** de 54 scénarios (Blocs A–J).

---

## 2. Participants

### 2.1 Présents

| Nom et prénom | Fonction | Structure | Signature |
|---------------|----------|-----------|-----------|
| Sylvie **NGUEMA ADZOVÉ** | Resp. PAP / Suivi-évaluation — Animatrice MOA | Direction Planification & S&E | |
| Paul-André **MBENG MBA** | Contrôleur financier | Direction du Contrôle financier | |
| Grace **ONDO** | Gestionnaire budgétaire | Direction du Budget | |
| Jean-Claude **NKOGHE NDONG** | Chef de projet SI | DSI | |
| Arsène **BIKANG** | Intégrateur MOE — Support technique | DSI — Projet BUDGET-PAP | |
| [Nom] | [Fonction] | [Structure] | |
| [Nom] | [Fonction] | [Structure] | |

**Effectif présent :** [__] personnes sur [__] convoquées.

### 2.2 Excusés / absents

| Nom | Motif | Représenté par |
|-----|-------|----------------|
| Daniel **ESSAME ESONO** | [Motif] | [Nom ou —] |
| [Nom] | [Motif] | [Nom ou —] |

### 2.3 Animateurs et secrétariat

| Rôle | Nom | Contact |
|------|-----|---------|
| Animatrice MOA | Sylvie NGUEMA ADZOVÉ | s.nguema@ceeac.int |
| Support MOE | Arsène BIKANG | a.bikang@ceeac.int |
| Rédacteur CR | [Nom] | [e-mail] |

---

## 3. Déroulé de la session

| Créneau | Thème prévu | Réalisé ☐ | Durée réelle | Commentaire |
|---------|-------------|-----------|--------------|-------------|
| 14h00 – 14h10 | Contexte audit et parcours P0→P3 | ☐ | [__ min] | |
| 14h10 – 14h35 | Hub S&E : scores, évaluations, clôture campagne | ☐ | [__ min] | |
| 14h35 – 14h55 | Missions, plans d'amélioration, actions correctives | ☐ | [__ min] | |
| 14h55 – 15h10 | Rapport institutionnel S&E (PDF) | ☐ | [__ min] | |
| 15h10 – 15h20 | Pause | ☐ | [__ min] | |
| 15h20 – 15h40 | Aperçu API REST (jeton scope « suivi ») | ☐ | ☐ NA | |
| 15h40 – 15h55 | Questions / manipulation libre | ☐ | [__ min] | |
| 15h55 – 16h00 | Prochaines étapes — recette 09/07 | ☐ | [__ min] | |

**Écarts au programme :** [Aucun / décrire]

---

## 4. Parcours démontré — synthèse technique

| # | Écran / action | URL ou commande | Statut démo | Remarque MOA |
|---|----------------|-----------------|-------------|--------------|
| 1 | Hub S&E — score global | `/suivi-evaluation?exercice=2026` | ☐ OK ☐ KO ☐ Partiel | |
| 2 | Génération évaluation annuelle | Bouton « Générer » / service | ☐ OK ☐ KO ☐ Partiel | |
| 3 | Détail snapshot | `/suivi-evaluation/evaluations/{id}` | ☐ OK ☐ KO ☐ Partiel | |
| 4 | Liste missions | `/suivi-evaluation/missions` — MS-2026-001 | ☐ OK ☐ KO ☐ Partiel | |
| 5 | Plan d'amélioration | `/suivi-evaluation/plans/{id}` — PA-2026-001 | ☐ OK ☐ KO ☐ Partiel | |
| 6 | Capture auto clôture campagne | Campagne suivi physique | ☐ OK ☐ KO ☐ NA ☐ Partiel | |
| 7 | Rapport PDF `suivi_evaluation` | Module Rapports | ☐ OK ☐ KO ☐ Partiel | |
| 8 | API synthèse | `GET /api/v1/suivi-evaluation/synthese?exercice=2026` | ☐ OK ☐ KO ☐ NA ☐ Partiel | |

**Données démo constatées :**

| Entité | Référence attendue | Présente ☐ |
|--------|-------------------|------------|
| Évaluation annuelle | AN-2026 | ☐ |
| Mission | MS-2026-001 | ☐ |
| Plan d'amélioration | PA-2026-001 | ☐ |
| Recommandations liées | REC-2026-006 / REC-2026-008 | ☐ |

---

## 5. Retours et questions MOA

### 5.1 Points appréciés

1. [Ex. clarté du hub, lisibilité des scores, lien missions ↔ plans]
2. […]
3. […]

### 5.2 Questions posées et réponses données

| # | Question (MOA) | Réponse / engagement MOE | Traitement recette |
|---|----------------|--------------------------|-------------------|
| Q1 | [Question] | [Réponse] | ☐ Clos   ☐ À vérifier le 09/07   ☐ Évolution |
| Q2 | [Question] | [Réponse] | ☐ Clos   ☐ À vérifier le 09/07   ☐ Évolution |
| Q3 | [Question] | [Réponse] | ☐ Clos   ☐ À vérifier le 09/07   ☐ Évolution |

### 5.3 Réserves ou demandes d'évolution *(hors périmètre recette)*

| Réf. | Description | Priorité | Décision |
|------|-------------|----------|----------|
| EVOL-01 | [Description] | ☐ P1 ☐ P2 ☐ P3 | ☐ Reporté post-MEP   ☐ À chiffrer |
| EVOL-02 | [Description] | ☐ P1 ☐ P2 ☐ P3 | ☐ Reporté post-MEP   ☐ À chiffrer |

---

## 6. Incidents et anomalies constatés

| # | Heure | Description | Gravité | Action immédiate | Statut J+2 |
|---|-------|-------------|---------|------------------|------------|
| INC-01 | [__h__] | [Description] | ☐ Bloquant ☐ Majeur ☐ Mineur | [Action] | ☐ Résolu ☐ En cours |
| INC-02 | [__h__] | [Description] | ☐ Bloquant ☐ Majeur ☐ Mineur | [Action] | ☐ Résolu ☐ En cours |

**Impact sur la recette du 09/07 :** ☐ Aucun   ☐ Report partiel   ☐ Report total *(motiver)*

---

## 7. Préparation recette MOA — actions avant le 09/07/2026

| # | Action | Responsable | Échéance | Fait ☐ |
|---|--------|-------------|----------|--------|
| 1 | Corriger anomalies INC-* | A. BIKANG | 07/07/2026 | |
| 2 | Rejouer tests automatisés (20 tests S&E) | A. BIKANG | 07/07/2026 | |
| 3 | Vérifier jeu démo (seeder, permissions) | A. BIKANG | 08/07/2026 | |
| 4 | Imprimer PV + grilles individuelles | C. MBOUMBA | 08/07/2026 | |
| 5 | Confirmer présence commission recette | C. MBOUMBA | 04/07/2026 | |
| 6 | Diffuser présent CR aux membres | S. NGUEMA | 05/07/2026 | |
| 7 | Prioriser scénarios sensibles (Blocs A, B, F) | S. NGUEMA / P. MBENG | 08/07/2026 | |
| 8 | Imprimer checklist animateur recette | C. MBOUMBA | 08/07/2026 | |

**Scénarios à accentuer lors de la recette** *(suite aux retours démo)* :

| Bloc PV | Thème | Scénarios concernés | Motif |
|---------|-------|---------------------|-------|
| A | Hub & scores | [ex. A-01 à A-08] | [Motif] |
| B | Évaluations périodiques | [ex. B-03, B-07] | [Motif] |
| F | Permissions & audit | [ex. F-02] | [Motif] |

---

## 8. Décision MOA sur la suite

☐ **Recette maintenue** le **09/07/2026** selon PV-REC-SE-2026-01  
☐ **Recette reportée** au [JJ/MM/AAAA] — motif : […]  
☐ **Complément démo** souhaité le [JJ/MM/AAAA] — thème : […]

**Conditions particulières pour valider la recette :**

> [Ex. « Aucune NC bloquante sur Blocs A, B, F » — reprendre seuil du PV]

---

## 9. Prochaines échéances

| Date | Événement | Référence | Contact |
|------|-----------|-----------|---------|
| **ven. 04/07/2026** | Synthèse retours → préparation recette | — | s.nguema@ceeac.int |
| **mar. 08/07/2026** | Rappel recette MOA (J-1) | PV-REC-SE-2026-01 | c.mboumba@ceeac.int |
| **mer. 09/07/2026** 08h30–16h30 | **Recette MOA** | PV-REC-SE-2026-01 | recette-se@ceeac.int |

---

## 10. Diffusion

| Destinataire | E-mail | Date envoi | Fait ☐ |
|--------------|--------|------------|--------|
| Commission recette (§ 3 PV) | cf. annuaire | [JJ/MM/AAAA] | |
| Archives MOA | archives-moa@ceeac.int | [JJ/MM/AAAA] | |
| MOE / DSI | j.nkoghe@ceeac.int, a.bikang@ceeac.int | [JJ/MM/AAAA] | |

### Modèle d'e-mail de diffusion

**Objet :** `[CEEAC] Compte-rendu démo Suivi-Évaluation — CR-DEMO-SE-2026-01`

```
Madame, Monsieur,

Veuillez trouver ci-joint le compte-rendu de la démonstration du module
Suivi-Évaluation (DEMO-SE-2026-01), tenue le 3 juillet 2026 au Lab SI.

Les actions identifiées avant la recette MOA du 9 juillet (PV-REC-SE-2026-01)
sont listées au § 7 du document.

Merci de confirmer votre disponibilité pour la recette à recette-se@ceeac.int
avant le 4 juillet 2026.

Cordialement,

Sylvie NGUEMA ADZOVÉ
Responsable PAP / Suivi-évaluation — CEEAC
s.nguema@ceeac.int · +241 62 11 34 08
```

---

## 11. Signatures

| Rôle | Nom | Date | Signature |
|------|-----|------|-----------|
| Rédacteur | [Nom] | [JJ/MM/AAAA] | |
| Validatrice MOA | Sylvie NGUEMA ADZOVÉ | [JJ/MM/AAAA] | |
| Visa MOE | Arsène BIKANG | [JJ/MM/AAAA] | |

---

## Annexes

| Réf. | Document |
|------|----------|
| A | [checklist-animateur-DEMO-SE-2026-01.md](checklist-animateur-DEMO-SE-2026-01.md) — complétée et signée |
| B | Captures d'écran démo *(optionnel — dossier partagé DSI)* |
| C | [pv-recette-suivi-evaluation.md](pv-recette-suivi-evaluation.md) — grille recette |
| D | [checklist-animateur-PV-REC-SE-2026-01.md](checklist-animateur-PV-REC-SE-2026-01.md) — complétée et signée |
| E | [annuaire-recette-suivi-evaluation-2026.md](annuaire-recette-suivi-evaluation-2026.md) |

---

*Document généré à partir du dépôt MOE — BUDGET-PAP-SUIVI-CEEAC — Juillet 2026.*
