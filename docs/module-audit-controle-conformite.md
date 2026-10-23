# Module « Audit, Contrôle Interne & Conformité » — BUDGET-PAP-SUIVI-CEEAC

Audit transversal (2026-06-14) aligné COSO / ISO 31000 / INTOSAI. **Constat : socle technique fort,
couche gouvernance formelle à construire.** Premier lot livré : **Lot A — Gestion des risques**.

## 1. État de conformité (synthèse d'audit)

| Axe | Conformité | Détail |
|---|---|---|
| Piste d'audit | ~90 % ✅ | `journaux_audit` (avant/après, ip, url, user_agent, immuable) sur 30 modèles ; login_logs ; api_logs ; audit_workflow ; VisaElectronique |
| Activités de contrôle | ~95 % ✅ | `peutEngager`, réservation atomique, CHECK MySQL `chk_lb_chaine_credits`, `parent_statut_requis`, blocages `peutSoumettre` |
| Contrôle interne de facto | ~85 % ✅ | 8 services d'audit (Depense/Suivi/Budget/Pap/Rbm/Compteurs…) avec score 0-100 + anomalies persistées |
| Séparation des fonctions | ✅ | `assertSeparationDesFonctions` (4 yeux), Delegation, Interim |
| **Gestion des risques** | ~25 % → **~70 %** | Lot A livré (registre central + cartographie + agrégation existants) |
| COSO formel / Conformité / Missions d'audit | ❌ | Lots B/C à planifier |

## 2. Lot A livré — Registre & Cartographie des risques (ISO 31000)

**Non destructif** : migration additive `2026_07_30_000001` (créations conditionnelles), aucune table existante modifiée.

### Modèle de données
- `categories_risques` (8 catégories normées : stratégique, financier, budgétaire, opérationnel, informatique, juridique, conformité, fraude).
- `risques` : code, catégorie, propriétaire, structure, exercice, processus, **probabilité (1-4)**, **impact (1-4)**, mesures de maîtrise, statut (identifié→en_traitement→maîtrisé→clôturé), softDeletes, `Auditable`.
- **Criticité = Probabilité × Impact** (1-16) ; niveau : Faible ≤3 · Modéré 4-7 · Élevé 8-11 · Critique ≥12.

### Service `App\Services\Risque\RisqueService`
- `cartographie()` : matrice 4×4 (heatmap), comptage par cellule + par niveau.
- `statistiques()`, `existantsNormalises()` : **agrège les risques stratégiques (`risques_strategiques`) et PAP (`pap_risques`) existants** (lecture seule, mapping enum→échelle 1-4) dans la vue consolidée.

### Interfaces (`RisqueController`)
- `GET /risques` — registre filtrable (catégorie/niveau/statut/recherche) + cartes par niveau.
- `GET /risques/cartographie` — **heatmap** de criticité (4×4 colorée) + risques existants agrégés.
- CRUD : create/store/edit/update/show/archiver (soft delete).
- Menu « Registre des risques » (section Sécurité & Accès), gaté `risques.voir`.

### Sécurité
- Permissions `risques.{voir,creer,modifier,supprimer,valider,exporter,…}` (module ajouté au `RoleSeeder`).
- Accordées : administrateur (toutes), **auditeur** (creer/modifier/supprimer/valider/exporter), directeur (creer/modifier/valider), lecteur (voir via `*.voir`).
- **Seeder `AuditControleConformiteSeeder`** (idempotent) : permissions + 8 catégories. Commande :
  `php artisan db:seed --class=AuditControleConformiteSeeder`. Intégré à `DatabaseSeeder`.

### Tests — `tests/Feature/RisqueTest.php` (6)
Catégories+permissions seedées, criticité/niveau, CRUD HTTP, comptage des cellules de la cartographie,
agrégation des risques PAP existants, séparation des permissions (lecteur lit / ne crée pas).

## 2bis. Lot E livré — Durcissement de la piste d'audit & séparation des fonctions

**Non destructif** : migration additive `2026_01_02_000002_add_role_to_journaux_audit` (placée juste après
la création de `journaux_audit` pour que la colonne existe avant toute écriture auditée lors des migrations
suivantes — sinon les migrations qui créent des modèles `Auditable` échoueraient).

- **Rôle figé** : `journaux_audit.role` (string, nullable). `AuditLogger` enregistre désormais le ou les
  rôle(s) de l'acteur au moment de l'action (`getRoleNames()`), garantissant une traçabilité résistante
  aux modifications ultérieures d'habilitation. Colonne `role` ajoutée au modèle `JournalAudit` et à
  l'écran « Piste d'audit ».
- **Journalisation des violations SoD** : `WorkflowService::assertSeparationDesFonctions` enregistre une
  entrée `sod_violation` (hors transaction → persiste malgré le refus) lorsqu'un même acteur tente de
  valider deux étapes consécutives, avant de lever l'exception. Lignes surlignées en rouge dans l'écran
  d'audit. La tentative bloquée est ainsi tracée (acteur, rôle figé, workflow, étape, motif).
- Tests : `tests/Feature/AuditTrailHardeningTest.php` (2) — rôle figé dans le journal ; violation SoD
  journalisée sur un circuit BESOIN à 3 étapes (acteur cumulant chef_service + directeur).

## 2ter. Lot D livré — Cockpit de gouvernance + export du registre des risques

**Non destructif** (aucune migration ; agrégation lecture seule de l'existant).

- **`App\Services\Gouvernance\GouvernanceService`** : agrège, de façon **résiliente (try/catch par domaine)**,
  les services d'audit `DepenseAuditService`, `SuiviAuditService`, `BudgetAuditService`, `PapAuditService`,
  `RbmAuditService` (normalisés en {domaine, score, anomalies, critiques}), + le registre des risques
  (`RisqueService`) + les indicateurs de piste d'audit (`journaux_audit` : événements, **violations SoD**,
  top actions, derniers ; `login_logs` réussies/échouées). Score global = moyenne des scores disponibles.
- **`GouvernanceController@cockpit`** → `GET /gouvernance/cockpit` (`gouvernance.cockpit`), gaté `audit.voir`.
  Vue `gouvernance/cockpit.blade.php` : bandeau (score global, risques élevés/critiques, violations SoD,
  événements) + conformité par domaine + synthèse risques + KPIs piste d'audit. Menu « Cockpit gouvernance ».
- **Export du registre des risques** : `risques.export/{xlsx|csv|pdf}` (`RisquesExport` Excel/CSV ;
  gabarit `pdf/risques` paysage), gaté `risques.exporter`, boutons sur l'écran registre.
- Tests : `tests/Feature/GouvernanceCockpitTest.php` (4) — agrégation conformité (5 domaines + score global),
  rendu des sections du cockpit, refus sans `audit.voir`, export PDF + Excel.

## 2quater. Lot B livré — Missions d'audit, constats & recommandations (INTOSAI)

**Non destructif** — migration additive `2026_07_31_000001` (créations conditionnelles).

- **Tables** : `missions_audit` (numero `MA-{annee}-NNNN`, type interne/externe/conformité/performance/financier,
  entité auditée, chef de mission, objectif/périmètre, dates, statut planifiée→en_cours→clôturée/annulée,
  conclusion, score) + `constats_audit` (numero `CA-{annee}-NNNN`, gravité, **recommandation + responsable +
  échéance + statut + taux d'avancement** = suivi intégré). Modèles `MissionAudit`/`ConstatAudit` (Auditable+SoftDeletes).
- **Cycle INTOSAI** : `MissionAuditController` — planifier (audit.creer) → exécuter/ajouter constats →
  suivre les recommandations (taux d'avancement, audit.modifier) → **clôturer** (audit.valider). **Contrôle
  métier** : clôture **bloquée** tant que des constats/recommandations restent ouverts.
- **Routes** `audit-missions.*` ; vues `audit-missions/{index,form,show}` (show = fiche + clôture + constats
  inline + suivi des recommandations). Menu « Missions d'audit ».
- **Permissions** : le module `audit` existait (10 actions) ; `audit.creer/modifier/valider` désormais
  **accordées au rôle auditeur** (RoleSeeder + AuditControleConformiteSeeder).
- **Intégration cockpit (Lot D)** : carte « Missions d'audit » (ouvertes/clôturées, recommandations en attente)
  via `GouvernanceService::missionsAudit()`.
- Tests : `tests/Feature/MissionAuditTest.php` (4) — planification, cycle constat→recommandation→suivi,
  clôture bloquée puis autorisée, permissions.

## 2quinquies. Lot C livré — Contrôle interne COSO + non-conformités / remédiation

**Non destructif** — migration additive `2026_07_31_000002` (créations conditionnelles).

- **COSO** : `controles_internes` — dispositif de contrôle **cartographié par composante COSO** (les 5 :
  environnement, évaluation des risques, activités de contrôle, information & communication, pilotage),
  type (préventif/détectif/correctif), fréquence, **évaluation d'efficacité** (efficace/partiel/inefficace/
  non_évalué). `ControleInterneController` : index = **cartographie des 5 composantes** (couverture +
  efficacité) + CRUD + `evaluer()`. Modèle `ControleInterne`.
- **Conformité** : `non_conformites` — écarts (source contrôle interne/audit/réglementaire, gravité,
  processus) + **plan de remédiation intégré** (action, responsable, échéance, statut, taux d'avancement).
  `NonConformiteController` : index (taux de conformité, ouvertes, critiques) + CRUD + show + `suivi()`
  (avancement de la remédiation). Modèle `NonConformite`.
- **Routes** `controle-interne.*` & `conformite.*` ; vues dédiées ; menu « Contrôle interne (COSO) » &
  « Non-conformités ».
- **Permissions** : modules `controle_interne` & `conformite` ajoutés au catalogue (RoleSeeder), accordés
  à l'auditeur (creer/modifier/valider) et au directeur (voir/conformité), admin (tout), lecteur (voir).
  AuditControleConformiteSeeder enrichi.
- **Cockpit (Lot D)** : carte « Contrôle interne (COSO) & conformité » (composantes couvertes, efficacité,
  **taux de conformité**, non-conformités ouvertes/critiques) via `GouvernanceService::controleInterne()`
  et `::conformite()`.
- Tests : `tests/Feature/ControleInterneConformiteTest.php` (4) — contrôle COSO + évaluation, cartographie,
  cycle non-conformité→remédiation→résolution (taux), permissions.

## 3. Bilan du module

| Lot | Objet | État |
|---|---|---|
| **A** | Registre & cartographie des risques (ISO 31000) | ✅ |
| **B** | Missions d'audit / constats / recommandations (INTOSAI) | ✅ |
| **C** | Contrôle interne COSO + non-conformités / remédiation | ✅ |
| **D** | Cockpit gouvernance + export | ✅ |
| **E** | Durcissement piste d'audit + SoD | ✅ |

**Conformité globale du module : ~45 % (audit initial) → ~85 %.** Le dispositif central de gouvernance,
maîtrise des risques, contrôle interne et conformité est désormais en place, intégré à la piste d'audit
existante et aux 8 services d'audit de conformité. 20 tests dédiés verts ; aucune régression
(DonneesDemoTest, 1702 assertions).
