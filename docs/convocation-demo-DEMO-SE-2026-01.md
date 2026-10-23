# Convocation — Démonstration Suivi-Évaluation

**Application :** BUDGET-PAP-SUIVI-CEEAC  
**Référence démo :** DEMO-SE-2026-01  
**Recette MOA associée :** PV-REC-SE-2026-01 *(09/07/2026)*  
**Annuaire complet :** [annuaire-recette-suivi-evaluation-2026.md](annuaire-recette-suivi-evaluation-2026.md)

---

## Informations session démo

| Élément | Valeur |
|---------|--------|
| **Date** | **Jeudi 3 juillet 2026** |
| **Horaires** | **14h00 – 16h00** |
| **Lieu** | Siège CEEAC — Libreville — **Bâtiment Informatique (DSI)** — **Salle de démonstration « Lab SI »** (rez-de-chaussée, aile Nord) |
| **Exercice présenté** | 2026 |
| **Environnement** | Préproduction — `https://budget-pap-preprod.ceeac.int` |
| **Format** | Présentation guidée + manipulation libre (30 min) |
| **Confirmation avant** | Mardi **1er juillet 2026** → **demo-se@ceeac.int** |

---

## A — E-mail de convocation démo

### Objet

```
[CEEAC] Invitation — Démo Suivi-Évaluation — 03/07/2026
```

### Destinataires

- **À :** Sylvie NGUEMA ADZOVÉ (s.nguema@ceeac.int), Paul-André MBENG MBA (p.mbeng@ceeac.int), Grace ONDO (g.ondo@ceeac.int)
- **Cc :** Jean-Claude NKOGHE NDONG (j.nkoghe@ceeac.int), Arsène BIKANG (a.bikang@ceeac.int)
- **Optionnel :** Daniel ESSAME ESONO (d.essame@ceeac.int)

### Corps

```
Madame, Monsieur,

Avant la session de recette MOA du 9 juillet 2026 (PV-REC-SE-2026-01), la Direction
Planification & Suivi-évaluation et la DSI vous convient à une démonstration du module :

  • Suivi-Évaluation (hub, scores, évaluations, missions, plans d'amélioration)

Référence démo : DEMO-SE-2026-01

────────────────────────────────────────────────────────────
INFORMATIONS PRATIQUES
────────────────────────────────────────────────────────────

Date      : jeudi 3 juillet 2026
Horaires  : 14h00 – 16h00
Lieu      : Siège CEEAC — Libreville
            Bâtiment Informatique (DSI)
            Salle de démonstration « Lab SI » — RDC, aile Nord
URL       : https://budget-pap-preprod.ceeac.int
Exercice  : 2026 (données démo MS-2026-001 / PA-2026-001)

────────────────────────────────────────────────────────────
ORDRE DE LA DÉMO
────────────────────────────────────────────────────────────

14h00 – 14h10   Contexte audit et parcours P0→P3
14h10 – 14h35   Hub S&E : scores, évaluations, clôture campagne
14h35 – 14h55   Missions, plans d'amélioration, actions correctives
14h55 – 15h10   Rapport institutionnel S&E (PDF)
15h10 – 15h20   Pause
15h20 – 15h40   Aperçu API REST (jeton scope « suivi »)
15h40 – 15h55   Questions / manipulation libre
15h55 – 16h00   Prochaines étapes — recette MOA du 09/07

────────────────────────────────────────────────────────────
PRÉPARATION
────────────────────────────────────────────────────────────

• Confirmer votre présence avant le 01/07/2026 à demo-se@ceeac.int
• Les identifiants de démo seront remis sur place (fiche papier — cf. annuaire)

Animateurs :
  Sylvie NGUEMA ADZOVÉ — MOA Suivi-évaluation
  Arsène BIKANG — MOE / intégrateur

Cordialement,

Sylvie NGUEMA ADZOVÉ
Responsable PAP / Suivi-évaluation — CEEAC
s.nguema@ceeac.int · +241 62 11 34 08
```

---

## B — Feuille à afficher — Lab SI (03/07/2026)

```
╔══════════════════════════════════════════════════════════════════╗
  DÉMONSTRATION — BUDGET-PAP-SUIVI-CEEAC
  Module Suivi-Évaluation (P0 → P3)
  DEMO-SE-2026-01  |  JEUDI 3 JUILLET 2026  |  14h00 – 16h00
  Lab SI — Bâtiment Informatique (DSI) — RDC aile Nord
╚══════════════════════════════════════════════════════════════════╝

Animatrice MOA : Sylvie NGUEMA ADZOVÉ
Support MOE  : Arsène BIKANG — +241 66 55 12 07
Recette MOA  : 09/07/2026 — PV-REC-SE-2026-01
Wi-Fi        : CEEAC-GUEST / CEEAC2026!
```

---

## C — Parcours démo recommandé (MOE)

| Étape | URL / action | Message clé |
|-------|--------------|-------------|
| 1 | `/suivi-evaluation?exercice=2026` | Score global + 5 composantes |
| 2 | Générer évaluation annuelle | Snapshot persisté |
| 3 | `/suivi-evaluation/evaluations/{id}` | Détail scores |
| 4 | `/suivi-evaluation/missions` | MS-2026-001 |
| 5 | `/suivi-evaluation/plans/{id}` | PA-2026-001 + actions |
| 6 | Clôture campagne (si ouverte) | Capture auto campagne |
| 7 | Rapport `suivi_evaluation` PDF | Cohérence hub |
| 8 | API `GET /api/v1/suivi-evaluation/synthese?exercice=2026` | Intégration SI |

**Prérequis MOE (J-1) :**

```bash
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\SafeDatabaseSeeder
php artisan permissions:sync-production --force
```

---

## D — Calendrier secrétariat démo

| Date | Action | Responsable | Fait ☐ |
|------|--------|-------------|--------|
| **ven. 27/06** | Envoyer convocation démo | C. MBOUMBA | |
| **mar. 01/07** | Confirmations + fiches identifiants | C. MBOUMBA / A. BIKANG | |
| **mer. 02/07** | Vérifier Lab SI + jeu démo | A. BIKANG | |
| **jeu. 03/07** | Démo + checklist animateur signée | S. NGUEMA / A. BIKANG | |
| **ven. 04/07** | Synthèse retours → préparation recette | S. NGUEMA ADZOVÉ | |
| **ven. 05/07** | Rédaction & diffusion CR-DEMO-SE-2026-01 | S. NGUEMA ADZOVÉ | |

---

## E — Documents liés

| Document | Lien |
|----------|------|
| Checklist animateur (imprimer) | [checklist-animateur-DEMO-SE-2026-01.md](checklist-animateur-DEMO-SE-2026-01.md) |
| Compte-rendu post-démo | [compte-rendu-demo-DEMO-SE-2026-01.md](compte-rendu-demo-DEMO-SE-2026-01.md) |
| Procès-verbal recette | [pv-recette-suivi-evaluation.md](pv-recette-suivi-evaluation.md) |
| Annuaire contacts | [annuaire-recette-suivi-evaluation-2026.md](annuaire-recette-suivi-evaluation-2026.md) |
