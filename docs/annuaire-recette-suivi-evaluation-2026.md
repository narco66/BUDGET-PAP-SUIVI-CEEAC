# Annuaire — Recette & démo Suivi-Évaluation 2026

**Usage interne CEEAC** — contacts pour convocations DEMO-SE-2026-01 et PV-REC-SE-2026-01.

| Rôle | Nom complet | E-mail | Téléphone | Structure |
|------|-------------|--------|-----------|-----------|
| **Présidente de séance / Resp. Suivi-évaluation** | Mme **Sylvie NGUEMA ADZOVÉ** | s.nguema@ceeac.int | +241 62 11 34 08 | Direction Planification & Suivi-évaluation |
| Contrôleur financier *(membre commission)* | M. **Paul-André MBENG MBA** | p.mbeng@ceeac.int | +241 01 44 47 20 · mob. +241 62 10 45 33 | Direction du Contrôle financier |
| Gestionnaire budgétaire | Mme **Grace ONDO** | g.ondo@ceeac.int | +241 62 08 91 26 | Direction du Budget |
| Secrétaire général *(représentant)* | M. **Daniel ESSAME ESONO** | d.essame@ceeac.int | +241 01 44 47 01 | Secrétariat Général |
| Chef de projet DSI | M. **Jean-Claude NKOGHE NDONG** | j.nkoghe@ceeac.int | +241 01 44 47 88 · mob. +241 66 20 03 14 | Direction des Systèmes d'Information |
| Intégrateur MOE | M. **Arsène BIKANG** | a.bikang@ceeac.int | +241 66 55 12 07 | DSI — Équipe projet BUDGET-PAP |
| Secrétariat de séance | Mme **Chantal MBOUMBA** | c.mboumba@ceeac.int | +241 01 44 47 22 | Assistante — Contrôle financier |
| Commissaire *(profil métier recette)* | Mme **Patience MBOUMBA OVONO** | p.mboumbo@ceeac.int | +241 62 14 22 05 | Cabinet du Commissaire |

## Boîtes fonctionnelles

| Usage | Adresse |
|-------|---------|
| Confirmations démo S&E | demo-se@ceeac.int |
| Confirmations recette MOA S&E | recette-se@ceeac.int |
| Archives / GED | archives-moa@ceeac.int |

## Calendrier des sessions

| Session | Référence | Date | Lieu |
|---------|-----------|------|------|
| Démo | DEMO-SE-2026-01 | **03/07/2026** 14h–16h | Lab SI — DSI RDC |
| Recette MOA | PV-REC-SE-2026-01 | **09/07/2026** 08h30–16h30 | Salle Planif. S&E — Bât. Admin. R+1 |

## Accès réseau

| Élément | Valeur |
|---------|--------|
| Wi-Fi invités | **CEEAC-GUEST** |
| Code session | **CEEAC2026!** *(remis aussi à l'accueil DSI)* |
| URL préproduction | https://budget-pap-preprod.ceeac.int |

## Comptes applicatifs démo *(sur place uniquement)*

| Profil | Identifiant | Rôle Spatie | Usage recette |
|--------|-------------|-------------|---------------|
| Responsable S&E | s.nguema@ceeac.test | directeur | Hub, missions, rapports |
| Commissaire | commissaire@ceeac.test | commissaire | Génération évaluations, validation |
| Chef de service | chef_service@ceeac.test | chef_service | Création missions / plans |
| Auditeur | auditeur@ceeac.test | auditeur | Recommandations, export |
| Administrateur SI | administrateur@ceeac.test | administrateur | Support technique |

*Les mots de passe ne figurent pas dans les e-mails — distribution papier ou clé USB chiffrée le jour J.*

## Données de démonstration attendues

| Entité | Référence | Source |
|--------|-----------|--------|
| Évaluation annuelle | AN-2026 | `SuiviEvaluationDemoSeeder` |
| Mission | MS-2026-001 | Seeder démo |
| Plan d'amélioration | PA-2026-001 | Seeder démo |
| Recommandation liée | REC-2026-006 / REC-2026-008 | `RecommandationsDemoSeeder` |

**Commande MOE (J-1 recette) :**

```bash
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\SafeDatabaseSeeder
php artisan permissions:sync-production --force
```

## Liens documents recette

| Document | Fichier |
|----------|---------|
| Procès-verbal (54 scénarios) | [pv-recette-suivi-evaluation.md](pv-recette-suivi-evaluation.md) |
| Convocation recette | [convocation-recette-PV-REC-SE-2026-01.md](convocation-recette-PV-REC-SE-2026-01.md) |
| Convocation démo | [convocation-demo-DEMO-SE-2026-01.md](convocation-demo-DEMO-SE-2026-01.md) |
| Checklist animateur démo | [checklist-animateur-DEMO-SE-2026-01.md](checklist-animateur-DEMO-SE-2026-01.md) |
| Compte-rendu démo | [compte-rendu-demo-DEMO-SE-2026-01.md](compte-rendu-demo-DEMO-SE-2026-01.md) |
| Checklist animateur recette | [checklist-animateur-PV-REC-SE-2026-01.md](checklist-animateur-PV-REC-SE-2026-01.md) |
| Module technique | [module-suivi-evaluation.md](module-suivi-evaluation.md) |
