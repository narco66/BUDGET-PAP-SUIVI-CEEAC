# Checklist animateur — Démo Suivi-Évaluation

**Référence :** DEMO-SE-2026-01  
**Date :** Jeudi **3 juillet 2026** — **14h00 – 16h00**  
**Lieu :** Lab SI — Bâtiment Informatique (DSI) — RDC aile Nord  
**URL :** https://budget-pap-preprod.ceeac.int · Exercice **2026**

> **Usage :** document **imprimable** (recto, A4). Cocher au stylo. Remettre l'exemplaire signé à la rédaction du CR **CR-DEMO-SE-2026-01** (Annexe A).

| Animatrice MOA | Support MOE |
|----------------|-------------|
| Sylvie NGUEMA ADZOVÉ — s.nguema@ceeac.int | Arsène BIKANG — a.bikang@ceeac.int — +241 66 55 12 07 |

---

## A — J-3 à J-1 *(mer. 02/07 au plus tard)*

| ☐ | Tâche | Responsable | Constat |
|---|-------|-------------|---------|
| ☐ | Convocation démo envoyée (27/06) | C. MBOUMBA | |
| ☐ | Confirmations présence reçues (deadline 01/07 → demo-se@ceeac.int) | C. MBOUMBA | Présents : ___ / ___ |
| ☐ | Fiches comptes démo imprimées *(cf. annuaire § comptes)* | A. BIKANG | ___ exemplaires |
| ☐ | `php artisan migrate` sur préprod | A. BIKANG | ☐ OK ☐ KO |
| ☐ | `php artisan db:seed --class=Database\Seeders\SafeDatabaseSeeder` | A. BIKANG | ☐ OK ☐ KO |
| ☐ | `php artisan permissions:sync-production --force` | A. BIKANG | ☐ OK ☐ KO |
| ☐ | Données démo : MS-2026-001, PA-2026-001, AN-2026 visibles | A. BIKANG | ☐ OK ☐ KO |
| ☐ | Suite tests S&E (20 tests) — 100 % vert | A. BIKANG | ☐ OK ☐ KO |
| ☐ | Vidéoprojecteur + son Lab SI testés | A. BIKANG | ☐ OK ☐ KO |
| ☐ | Postes démo connectés (Wi-Fi CEEAC-GUEST / réseau) | A. BIKANG | ___ postes |
| ☐ | Feuille affichée entrée salle *(convocation § B)* | C. MBOUMBA | |
| ☐ | Rappel J-1 envoyé *(optionnel — 02/07)* | C. MBOUMBA | |

---

## B — Matériel jour J *(arrivée 13h30)*

| ☐ | Élément | Qté | OK |
|---|---------|-----|-----|
| ☐ | PC animateur + chargeur | 1 | ☐ |
| ☐ | PC backup MOE | 1 | ☐ |
| ☐ | Adaptateur HDMI / USB-C vidéoprojecteur | 1 | ☐ |
| ☐ | Fiches identifiants démo *(papier)* | ___ | ☐ |
| ☐ | Stylos + feuilles participants | 1 lot | ☐ |
| ☐ | Cette checklist (2 ex. : MOA + MOE) | 2 | ☐ |
| ☐ | Clé USB secours export PDF rapport | 1 | ☐ |
| ☐ | Jeton API test *(scope suivi)* — noté hors-ligne | 1 | ☐ |

**Comptes à tester avant ouverture (connexion rapide) :**

| ☐ | Profil | Identifiant |
|---|--------|-------------|
| ☐ | Responsable S&E | s.nguema@ceeac.test |
| ☐ | Commissaire | commissaire@ceeac.test |
| ☐ | Chef de service | chef_service@ceeac.test |
| ☐ | Auditeur | auditeur@ceeac.test |

---

## C — Accueil *(13h45 – 14h00)*

| ☐ | Action | Heure | Note |
|---|--------|-------|------|
| ☐ | Accueil participants — registre présence | | ___ présents |
| ☐ | Distribution fiches comptes *(pas de MDP par oral collectif)* | | |
| ☐ | Rappel Wi-Fi : CEEAC-GUEST / CEEAC2026! | | |
| ☐ | Objectif session : préparation recette 09/07 (PV-REC-SE-2026-01) | | |
| ☐ | Ouverture hub sur vidéoprojecteur | | |

---

## D — Parcours démo *(cocher à chaque étape)*

| ☐ | # | Écran | URL / action | Durée cible | OK | Remarque |
|---|----|-------|--------------|-------------|-----|----------|
| ☐ | 1 | Contexte P0→P3 | Slides / oral | 10 min | ☐ | |
| ☐ | 2 | Hub S&E | `/suivi-evaluation?exercice=2026` | 5 min | ☐ | Score global visible |
| ☐ | 3 | 5 composantes score | Hub — détail cartes | 5 min | ☐ | Physique, IOV, reco… |
| ☐ | 4 | Générer évaluation | Bouton service MOA | 5 min | ☐ | Snapshot créé |
| ☐ | 5 | Détail évaluation | `/suivi-evaluation/evaluations/{id}` | 5 min | ☐ | |
| ☐ | 6 | Missions | `/suivi-evaluation/missions` | 5 min | ☐ | MS-2026-001 |
| ☐ | 7 | Plans + actions | `/suivi-evaluation/plans/{id}` | 5 min | ☐ | PA-2026-001 |
| ☐ | 8 | Clôture campagne *(si applicable)* | Module campagnes | 5 min | ☐ ☐ NA | Capture auto |
| ☐ | 9 | Rapport PDF | Rapport `suivi_evaluation` | 10 min | ☐ | Cohérent hub |
| ☐ | — | **PAUSE** | | 10 min | ☐ | |
| ☐ | 10 | API REST | `GET …/synthese?exercice=2026` | 15 min | ☐ ☐ NA | Postman / curl |
| ☐ | 11 | Manipulation libre | Postes participants | 15 min | ☐ | |
| ☐ | 12 | Prochaines étapes | Oral — recette 09/07 | 5 min | ☐ | |

**Commandes secours MOE (terminal hors projection) :**

```bash
php artisan suivi-evaluation:capture --exercice=2026
php artisan test tests/Feature/SuiviEvaluationP0Test.php --filter=test_hub
```

| ☐ | Commande exécutée si besoin | Résultat |
|---|----------------------------|----------|
| ☐ | capture | ☐ OK ☐ KO |
| ☐ | test rapide | ☐ OK ☐ KO |

---

## E — Gestion des incidents *(si besoin)*

| ☐ | Incident | Heure | Contournement | Résolu ☐ |
|---|----------|-------|---------------|----------|
| ☐ | | | | ☐ |
| ☐ | | | | ☐ |
| ☐ | | | | ☐ |

**Escalade :** J.-C. NKOGHE NDONG — j.nkoghe@ceeac.int — +241 66 20 03 14

---

## F — Clôture *(15h55 – 16h00)*

| ☐ | Action |
|---|--------|
| ☐ | Collecter questions écrites / oral — noter dans CR § 5 |
| ☐ | Rappeler date recette : **mer. 09/07/2026** 08h30 — Salle Planif. S&E |
| ☐ | Rappeler confirmation recette → **recette-se@ceeac.int** avant **04/07** |
| ☐ | Récupérer fiches comptes papier *(destruction ou remise accueil DSI)* |
| ☐ | Éteindre postes / vidéoprojecteur |
| ☐ | Remettre checklist signée pour rédaction CR *(deadline CR : 05/07)* |

---

## G — Synthèse express *(à remplir en fin de session)*

| Indicateur | Valeur |
|------------|--------|
| Participants présents | ___ |
| Parcours complet sans incident | ☐ Oui   ☐ Non |
| Recette maintenue au 09/07 | ☐ Oui   ☐ Report |
| Points bloquants identifiés | ☐ Aucun   ☐ Voir § E |
| Top 3 retours MOA | 1. _________________ 2. _________________ 3. _________________ |

---

## H — Signatures

| Rôle | Nom | Date | Signature |
|------|-----|------|-----------|
| Animatrice MOA | Sylvie NGUEMA ADZOVÉ | 03/07/2026 | |
| Support MOE | Arsène BIKANG | 03/07/2026 | |

---

## Documents liés

| Document | Fichier |
|----------|---------|
| Compte-rendu à rédiger | [compte-rendu-demo-DEMO-SE-2026-01.md](compte-rendu-demo-DEMO-SE-2026-01.md) |
| Convocation démo | [convocation-demo-DEMO-SE-2026-01.md](convocation-demo-DEMO-SE-2026-01.md) |
| Annuaire & comptes | [annuaire-recette-suivi-evaluation-2026.md](annuaire-recette-suivi-evaluation-2026.md) |
| PV recette | [pv-recette-suivi-evaluation.md](pv-recette-suivi-evaluation.md) |
| Checklist recette | [checklist-animateur-PV-REC-SE-2026-01.md](checklist-animateur-PV-REC-SE-2026-01.md) |

---

```
╔══════════════════════════════════════════════════════════════════╗
  CHECKLIST ANIMATEUR — DEMO-SE-2026-01
  Suivi-Évaluation · 03/07/2026 · Lab SI
  À joindre au CR-DEMO-SE-2026-01 (Annexe A)
╚══════════════════════════════════════════════════════════════════╝
```
