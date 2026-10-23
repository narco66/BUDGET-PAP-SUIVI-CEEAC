# Checklist animateur — Recette MOA Suivi-Évaluation

**Référence PV :** PV-REC-SE-2026-01  
**Date :** Mercredi **9 juillet 2026** — **08h30 – 16h30** *(pause 12h30 – 13h30)*  
**Lieu :** Salle Planification & Suivi-évaluation — Bâtiment Administratif — R+1 aile Ouest  
**URL :** https://budget-pap-preprod.ceeac.int · Exercice **2026**

> **Usage :** document **imprimable** (recto-verso, A4). Cocher au stylo. Conserver un exemplaire signé avec le **PV signé** (archives GED). Compléter en parallèle la grille §5 du procès-verbal.

| Présidente de séance (MOA) | Secrétariat | Support MOE |
|----------------------------|-------------|-------------|
| Sylvie NGUEMA ADZOVÉ — s.nguema@ceeac.int | Chantal MBOUMBA — c.mboumba@ceeac.int | Arsène BIKANG — a.bikang@ceeac.int |
| +241 62 11 34 08 | +241 01 44 47 22 | J.-C. NKOGHE NDONG — j.nkoghe@ceeac.int |

**Seuil MOA :** ≥ 95 % scénarios **C** · aucun **NC** bloquant sur **Blocs A, B, F**

---

## A — Préparation J-8 à J-1

| ☐ | Tâche | Responsable | Échéance | Constat |
|---|-------|-------------|----------|---------|
| ☐ | Convocation + PV PDF envoyés | C. MBOUMBA | 01/07 | |
| ☐ | CR-DEMO-SE-2026-01 diffusé (retours démo intégrés) | S. NGUEMA | 05/07 | |
| ☐ | Confirmations présence (deadline → recette-se@ceeac.int) | C. MBOUMBA | 07/07 | Présents : ___ / ___ |
| ☐ | Rappel J-2 envoyé (07/07) | C. MBOUMBA | 07/07 | |
| ☐ | PV vierges imprimés (1 / membre + 4 originaux) | C. MBOUMBA | 08/07 | ___ ex. |
| ☐ | Feuilles présence + feuille affichée salle | C. MBOUMBA | 08/07 | |
| ☐ | Salle Planif. R+1 Ouest réservée et testée | C. MBOUMBA | 08/07 | ☐ OK ☐ KO |
| ☐ | `php artisan migrate` préprod | A. BIKANG | 08/07 | ☐ OK ☐ KO |
| ☐ | `php artisan permissions:sync-production --force` | A. BIKANG | 08/07 | ☐ OK ☐ KO |
| ☐ | Jeu démo MS-2026-001 / PA-2026-001 / AN-2026 | A. BIKANG | 08/07 | ☐ OK ☐ KO |
| ☐ | 20 tests S&E — 100 % vert | A. BIKANG | 08/07 | ☐ OK ☐ KO |
| ☐ | `php artisan suivi-evaluation:capture --exercice=2026` | A. BIKANG | 08/07 | ☐ OK ☐ KO |
| ☐ | Campagne ouverte dispo pour scénario C2 *(ou NA documenté)* | A. BIKANG | 08/07 | ☐ OK ☐ NA |
| ☐ | Jeton API test (scope `suivi`) préparé | A. BIKANG | 08/07 | ☐ OK ☐ KO |
| ☐ | Captures écran préparées *(Annexe B PV)* | A. BIKANG | 08/07 | ___ / 8 |
| ☐ | Postman / curl API prêts (Bloc I) | A. BIKANG | 08/07 | |

---

## B — Matériel jour J *(arrivée 08h00)*

| ☐ | Élément | Qté | OK |
|---|---------|-----|-----|
| ☐ | PC présidente + vidéoprojection | 1 | ☐ |
| ☐ | PC backup MOE | 1 | ☐ |
| ☐ | Postes commission (1 / membre ou binôme) | ___ | ☐ |
| ☐ | Adaptateur HDMI / USB-C | 1 | ☐ |
| ☐ | PV vierges + stylos | 1 lot | ☐ |
| ☐ | Cette checklist (2 ex. : MOA + MOE) | 2 | ☐ |
| ☐ | Annexe A PV (contrôle scores manuel) | ___ ex. | ☐ |
| ☐ | Clé USB : PDF rapport, log tests, JSON API | 1 | ☐ |
| ☐ | Registre présence signé | 1 | ☐ |
| ☐ | Eau / café accueil | — | ☐ |

**Comptes testés avant ouverture :**

| ☐ | Profil | Identifiant | Bloc cible |
|---|--------|-------------|------------|
| ☐ | Responsable S&E | s.nguema@ceeac.test | A, B, E |
| ☐ | Commissaire | commissaire@ceeac.test | B, H |
| ☐ | Chef de service | chef_service@ceeac.test | E, F |
| ☐ | Auditeur | auditeur@ceeac.test | D, H |
| ☐ | Sans droit S&E *(403)* | [compte restreint] | A6, H |

---

## C — Accueil *(08h15 – 08h30)*

| ☐ | Action | Heure | Note |
|---|--------|-------|------|
| ☐ | Accueil commission — registre présence | | ___ présents |
| ☐ | Feuille affichée entrée salle *(convocation § C)* | | |
| ☐ | Distribution PV vierge + Annexe A scores | | |
| ☐ | Rappel Wi-Fi : CEEAC-GUEST / CEEAC2026! | | |
| ☐ | Rappel légende PV : C / NC / NA / R | | |
| ☐ | Rappel seuil : ≥ 95 % C · pas de NC bloquante A/B/F | | |
| ☐ | Hub ouvert sur vidéoprojecteur `/suivi-evaluation?exercice=2026` | | |

---

## D — Déroulé par créneaux *(cocher scénarios animés + résultat PV)*

### 08h30 – 08h45 · Ouverture (PV §1–§2)

| ☐ | Point | Animateur | Fait |
|---|-------|-----------|------|
| ☐ | Contexte audit ~48 % → plan P0–P3 | S. NGUEMA | ☐ |
| ☐ | Périmètre recetté / exclus | S. NGUEMA | ☐ |
| ☐ | Rappel CR démo 03/07 *(si applicable)* | S. NGUEMA | ☐ |
| ☐ | §4 PV : migrations / permissions / tests auto | A. BIKANG | ☐ |

---

### 08h45 – 10h15 · Blocs A & B — Hub et évaluations

| ☐ | ID | Scénario (résumé) | PV ☐C ☐NC ☐R ☐NA |
|---|-----|-------------------|-------------------|
| ☐ | A1 | Accès hub `/suivi-evaluation` | ☐ ☐ ☐ ☐ |
| ☐ | A2 | Score global + niveau | ☐ ☐ ☐ ☐ |
| ☐ | A3 | 5 composantes visibles | ☐ ☐ ☐ ☐ |
| ☐ | A4 | Cohérence calculateur | ☐ ☐ ☐ ☐ |
| ☐ | A5 | Filtre exercice | ☐ ☐ ☐ ☐ |
| ☐ | A6 | Refus sans permission (403) | ☐ ☐ ☐ ☐ |
| ☐ | B1 | Génération évaluation annuelle | ☐ ☐ ☐ ☐ |
| ☐ | B2 | Idempotence regénération | ☐ ☐ ☐ ☐ |
| ☐ | B3 | Détail évaluation `{id}` | ☐ ☐ ☐ ☐ |
| ☐ | B4 | Historique hub | ☐ ☐ ☐ ☐ |
| ☐ | B5 | Capture batch artisan | ☐ ☐ ☐ ☐ |
| ☐ | B6 | JSON `details` composantes | ☐ ☐ ☐ ☐ |

**Pause micro *(optionnelle 5 min)* :** ☐

---

### 10h15 – 12h30 · Blocs C, D, E, F — Campagnes à plans

| ☐ | ID | Scénario (résumé) | PV ☐C ☐NC ☐R ☐NA |
|---|-----|-------------------|-------------------|
| ☐ | C1 | Lien campagnes `/suivi` | ☐ ☐ ☐ ☐ |
| ☐ | C2 | Clôture campagne → snapshot | ☐ ☐ ☐ ☐ |
| ☐ | C3 | Score physique PAP | ☐ ☐ ☐ ☐ |
| ☐ | C4 | Score conformité audit | ☐ ☐ ☐ ☐ |
| ☐ | C5 | Couverture PAP | ☐ ☐ ☐ ☐ |
| ☐ | D1 | Registre recommandations | ☐ ☐ ☐ ☐ |
| ☐ | D2 | Champ `categorie` | ☐ ☐ ☐ ☐ |
| ☐ | D3 | Lien `evaluation_periodique_id` | ☐ ☐ ☐ ☐ |
| ☐ | D4 | Score recommandations | ☐ ☐ ☐ ☐ |
| ☐ | D5 | Stats hub recommandations | ☐ ☐ ☐ ☐ |
| ☐ | E1 | Liste missions | ☐ ☐ ☐ ☐ |
| ☐ | E2 | Création mission MS-2026-NNN | ☐ ☐ ☐ ☐ |
| ☐ | E3 | Types mission | ☐ ☐ ☐ ☐ |
| ☐ | E4 | Fiche mission MS-2026-001 | ☐ ☐ ☐ ☐ |
| ☐ | E5 | Lien évaluation | ☐ ☐ ☐ ☐ |
| ☐ | E6 | Stats hub missions | ☐ ☐ ☐ ☐ |
| ☐ | F1 | Liste plans | ☐ ☐ ☐ ☐ |
| ☐ | F2 | Création plan PA-2026-NNN | ☐ ☐ ☐ ☐ |
| ☐ | F3 | Ajout action corrective | ☐ ☐ ☐ ☐ |
| ☐ | F4 | Recalcul taux plan | ☐ ☐ ☐ ☐ |
| ☐ | F5 | Lien recommandation action | ☐ ☐ ☐ ☐ |
| ☐ | F6 | Chaîne Éval → Mission → Plan → Actions | ☐ ☐ ☐ ☐ |
| ☐ | F7 | Stats hub plans | ☐ ☐ ☐ ☐ |

**⚠ Bloc F — NC bloquante si échec F2, F3 ou F6**

---

### 12h30 – 13h30 · Pause déjeuner

| ☐ | Action |
|---|--------|
| ☐ | Verrouiller session / écran projeteur |
| ☐ | Point MOE : anomalies matin à corriger ? |

---

### 13h30 – 15h15 · Blocs G, H, I — Rapports, permissions, API

| ☐ | ID | Scénario (résumé) | PV ☐C ☐NC ☐R ☐NA |
|---|-----|-------------------|-------------------|
| ☐ | G1 | Rapport PDF `suivi_evaluation` | ☐ ☐ ☐ ☐ |
| ☐ | G2 | Export Excel | ☐ ☐ ☐ ☐ |
| ☐ | G3 | Export Word | ☐ ☐ ☐ ☐ |
| ☐ | G4 | KPI rapport = hub (≤ 0,1 pt) | ☐ ☐ ☐ ☐ |
| ☐ | G5 | Historique évaluations rapport | ☐ ☐ ☐ ☐ |
| ☐ | G6 | Section missions / plans rapport | ☐ ☐ ☐ ☐ |
| ☐ | H1 | Permission `view` / alias | ☐ ☐ ☐ ☐ |
| ☐ | H2 | Permission `validate` | ☐ ☐ ☐ ☐ |
| ☐ | H3 | Permission `create` | ☐ ☐ ☐ ☐ |
| ☐ | H4 | Rôle commissaire | ☐ ☐ ☐ ☐ |
| ☐ | H5 | Rôle chef_service | ☐ ☐ ☐ ☐ |
| ☐ | H6 | Traçabilité `Auditable` | ☐ ☐ ☐ ☐ |
| ☐ | H7 | ProductionPermissionsSeeder | ☐ ☐ ☐ ☐ |
| ☐ | I1 | Jeton scope `suivi` | ☐ ☐ ☐ ☐ |
| ☐ | I2 | API synthese | ☐ ☐ ☐ ☐ |
| ☐ | I3 | API evaluations | ☐ ☐ ☐ ☐ |
| ☐ | I4 | API missions | ☐ ☐ ☐ ☐ |
| ☐ | I5 | API plans | ☐ ☐ ☐ ☐ |
| ☐ | I6 | Refus sans permission API (403) | ☐ ☐ ☐ ☐ |
| ☐ | I7 | Refus scope `tompro` seul | ☐ ☐ ☐ ☐ |
| ☐ | I8 | Journalisation `api_logs` | ☐ ☐ ☐ ☐ |

---

### 15h15 – 16h00 · Bloc J — Intégrations transverses

| ☐ | ID | Scénario (résumé) | PV ☐C ☐NC ☐R ☐NA |
|---|-----|-------------------|-------------------|
| ☐ | J1 | Lien hub IOV | ☐ ☐ ☐ ☐ |
| ☐ | J2 | Composante IOV cohérente | ☐ ☐ ☐ ☐ |
| ☐ | J3 | Composante formations | ☐ ☐ ☐ ☐ |
| ☐ | J4 | Menus Campagnes ≠ Suivi-évaluation | ☐ ☐ ☐ ☐ |
| ☐ | J5 | Données démo seeder | ☐ ☐ ☐ ☐ |

---

### 16h00 – 16h30 · Synthèse, décision MOA, signatures (PV §6–§11)

| ☐ | Point | Heure | Fait |
|---|-------|-------|------|
| ☐ | §6 Synthèse quantitative — taux de réussite | 16h00 | ___ % |
| ☐ | §7 Anomalies / NC — plan correction | 16h05 | |
| ☐ | §8 Réserves acceptées | 16h10 | |
| ☐ | §9 Décision MOA cochée | 16h15 | ☐ Sans réserve ☐ Avec réserves ☐ Refusée |
| ☐ | Annexe A — contrôle scores manuel signé | 16h20 | |
| ☐ | §11 Signatures PV (4 originaux) | 16h20–16h30 | |
| ☐ | Feuille présence annexée au PV | 16h30 | |

**Signataires PV §11 :**

| ☐ | Signataire | Signé |
|---|------------|-------|
| ☐ | Sylvie NGUEMA ADZOVÉ (MOA) | ☐ |
| ☐ | Jean-Claude NKOGHE NDONG (MOE) | ☐ |
| ☐ | Paul-André MBENG MBA | ☐ |
| ☐ | Daniel ESSAME ESONO | ☐ |

---

## E — Synthèse quantitative *(reporter dans PV §6)*

| Bloc | Scénarios | C | NC | R | NA | Taux bloc |
|------|-----------|---|----|----|-----|-----------|
| A | 6 | | | | | ___ % |
| B | 6 | | | | | ___ % |
| C | 5 | | | | | ___ % |
| D | 5 | | | | | ___ % |
| E | 6 | | | | | ___ % |
| F | 7 | | | | | ___ % |
| G | 6 | | | | | ___ % |
| H | 7 | | | | | ___ % |
| I | 8 | | | | | ___ % |
| J | 5 | | | | | ___ % |
| **Total** | **54** | | | | | **___ %** |

**NC bloquantes Blocs A, B, F :** ☐ Aucune   ☐ Voir § F ci-dessous

**Décision seuil MOA atteint :** ☐ Oui (≥ 95 % C)   ☐ Non

---

## F — Incidents session *(reporter dans PV §7)*

| # | Heure | ID | Description | Gravité | Action | Résolu ☐ |
|---|-------|-----|-------------|---------|--------|----------|
| 1 | | | | ☐ Bloq. ☐ Maj. ☐ Min. | | ☐ |
| 2 | | | | ☐ Bloq. ☐ Maj. ☐ Min. | | ☐ |
| 3 | | | | ☐ Bloq. ☐ Maj. ☐ Min. | | ☐ |

**Escalade technique :** Arsène BIKANG — +241 66 55 12 07 · Jean-Claude NKOGHE NDONG — +241 66 20 03 14

---

## G — Post-recette J+1 *(10/07/2026)*

| ☐ | Action | Responsable | Fait |
|---|--------|-------------|------|
| ☐ | Scanner PV signé (4 originaux + registre) | C. MBOUMBA | |
| ☐ | Archiver GED → archives-moa@ceeac.int | C. MBOUMBA | |
| ☐ | Diffuser PV PDF aux membres commission | C. MBOUMBA | |
| ☐ | Plan correction NC *(si applicable)* | A. BIKANG | |
| ☐ | Si recette OK : lancer Annexe D MEP production | J.-C. NKOGHE | |
| ☐ | Point MOA levée réserves *(si §8)* | S. NGUEMA | |

---

## H — Signatures checklist

| Rôle | Nom | Date | Signature |
|------|-----|------|-----------|
| Présidente MOA | Sylvie NGUEMA ADZOVÉ | 09/07/2026 | |
| Secrétariat | Chantal MBOUMBA | 09/07/2026 | |
| Support MOE | Arsène BIKANG | 09/07/2026 | |

---

## Documents liés

| Document | Fichier |
|----------|---------|
| Procès-verbal (grille complète) | [pv-recette-suivi-evaluation.md](pv-recette-suivi-evaluation.md) |
| Convocation recette | [convocation-recette-PV-REC-SE-2026-01.md](convocation-recette-PV-REC-SE-2026-01.md) |
| CR démo 03/07 | [compte-rendu-demo-DEMO-SE-2026-01.md](compte-rendu-demo-DEMO-SE-2026-01.md) |
| Checklist démo | [checklist-animateur-DEMO-SE-2026-01.md](checklist-animateur-DEMO-SE-2026-01.md) |
| Annuaire | [annuaire-recette-suivi-evaluation-2026.md](annuaire-recette-suivi-evaluation-2026.md) |

---

```
╔══════════════════════════════════════════════════════════════════╗
  CHECKLIST ANIMATEUR — PV-REC-SE-2026-01
  Recette MOA Suivi-Évaluation · 09/07/2026 · 08h30 – 16h30
  Salle Planification & Suivi-évaluation — Bât. Admin. R+1 Ouest
  54 scénarios · Seuil MOA ≥ 95 % C · Blocs A/B/F sans NC bloquante
╚══════════════════════════════════════════════════════════════════╝
```
