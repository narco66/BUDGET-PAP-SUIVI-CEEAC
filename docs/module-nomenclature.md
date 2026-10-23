# Module Nomenclature Budgétaire — BUDGET-PAP-SUIVI-CEEAC

Gestion hiérarchique du référentiel budgétaire de la Commission de la CEEAC :
**Titre → Article → Paragraphe → Rubrique → Ligne budgétaire**. Intégration **non destructive** :
les tables, données, clés étrangères, historiques et workflows existants sont préservés ; seules des
colonnes ont été **ajoutées** (archivage logique + métadonnées de ligne).

## 1. Ce qui préexistait (réutilisé)

- Tables `budget_titres`, `budget_articles`, `budget_paragraphes`, `budget_rubriques`, `lignes_budgetaires`.
- Seeder `NomenclatureBudgetaireSeeder` (~500 rubriques, idempotent) — la nomenclature CEEAC réelle.
- CRUD des **lignes budgétaires** (module Budget) + import Excel + contrôle de crédits (`peutEngager`).
- Permissions `nomenclature.*` (RoleSeeder).

## 2. Ajouts de ce module (non destructifs)

Migration `2026_06_17_000001_nomenclature_archivage_et_metadonnees` (idempotente, `hasColumn`) :
- `deleted_at` (archivage logique) sur les 4 niveaux Titre/Article/Paragraphe/Rubrique.
- `nature` + `date_entree_vigueur` sur `lignes_budgetaires`.

Modèles : `SoftDeletes` + `Auditable` ajoutés aux 4 niveaux ; `BudgetTitre::SENS` (recette, dépense,
financement, hors-bilan). `LigneBudgetaire` : `scopeActif`, `estDisponible()` et **contrôle `actif`
dans `peutEngager()`** → une ligne désactivée ou archivée n'est plus engageable.

## 3. CRUD hiérarchique

Contrôleur générique unique `NomenclatureController` piloté par `{niveau}` (titre|article|paragraphe|
rubrique) — DRY, même patron que `RbmController` :

| Route | Nom | Rôle |
|---|---|---|
| GET /nomenclature | nomenclature.index | Arborescence + tableau des rubriques (recherche, filtres, archives) |
| GET /nomenclature/{niveau}/create | nomenclature.create | Formulaire de création |
| POST /nomenclature/{niveau} | nomenclature.store | Enregistrement |
| GET /nomenclature/{niveau}/{id} | nomenclature.show | Vue détaillée + éléments rattachés |
| GET /nomenclature/{niveau}/{id}/edit | nomenclature.edit | Édition |
| PUT /nomenclature/{niveau}/{id} | nomenclature.update | Mise à jour |
| DELETE /nomenclature/{niveau}/{id} | nomenclature.archiver | Archivage logique |
| POST /nomenclature/{niveau}/{id}/restaurer | nomenclature.restaurer | Restauration |

**3 vues** de la hiérarchie : arborescence dynamique (`<details>` repliables), vue tabulaire
(rubriques + chemin complet, recherche/filtre par sens/bascule archives), vue détaillée.

## 4. Contrôles métiers

- **Unicité** : code unique dans le périmètre du parent (sens+code pour titre ; parent_id+code pour les
  sous-niveaux) — validé côté applicatif (`Rule::unique`) et garanti par les contraintes DB existantes.
- **Cohérence hiérarchique** : parent obligatoire et existant (`Rule::exists`) pour article/paragraphe/rubrique.
- **Archivage sûr** : impossible d'archiver un élément qui possède encore des enfants (ou, pour une
  rubrique, des lignes rattachées) — message d'erreur, aucune suppression.
- **Disponibilité** : les lignes inactives/archivées sont exclues des opérations (`peutEngager` renvoie
  faux), donc non imputables dans la chaîne de la dépense.

## 5. Sécurité & traçabilité

- Permissions Spatie : `nomenclature.voir / creer / modifier / supprimer / exporter / importer`
  (accordées à l'administrateur et au gestionnaire budgétaire ; lecture pour tous via `*.voir`).
- Journalisation automatique (trait `Auditable`) des créations / modifications / archivages dans
  `journaux_audit`.

## 6. Reporting

Type `nomenclature` ajouté au module Reporting (mêmes garanties : hash, QR, signature, historisation,
versioning) : synthèse (comptes), synthèse par titre, nomenclature complète à plat.
**Formats : PDF, Excel et CSV** (le format CSV a été ajouté au moteur de rendu, `RapportCsv`).
Accès : hub Reporting → carte « Nomenclature budgétaire », ou menu Budgétisation → Nomenclature → Exporter.

## 7. Lots d'enrichissement (audit 2026-06-14)

Suite à l'audit de conformité au cahier des charges, quatre lots ont été ajoutés — toujours **non
destructifs** (migrations **uniquement additives**, aucun renommage de table ; le vocabulaire CDC
Chapitre/Ligne n'est appliqué qu'en **libellé d'affichage**, les tables `budget_titres`/`budget_rubriques`
sont conservées).

### L2 — Saisie assistée ligne par ligne
Écran `GET /nomenclature/assistant` (`nomenclature.assistant`). Le système détecte le niveau, propose
le **parent** et le **prochain code disponible** (heuristique : le code enfant prolonge le code parent,
`60 → 601 → 6011 → 60111`) et vérifie l'**unicité en temps réel**.
- AJAX : `nomenclature.assistant.contexte` (chemin parent + code suggéré + frères) et
  `nomenclature.assistant.verifier` (unicité, soft-deletes inclus).
- `nomenclature.store` répond désormais en JSON (`expectsJson`) pour **enchaîner la saisie** sans
  rechargement ; le comportement HTML (redirection) est inchangé.
- Interface vanilla JS (pas de dépendance Alpine), bouton « Saisie assistée » sur l'index.

### L3 — Tableau de bord nomenclature
Écran `GET /nomenclature/tableau-bord` (`nomenclature.tableau`). Service `NomenclatureStatsService` :
- **Volumétrie** : nb chapitres/articles/paragraphes/lignes + répartition des titres par sens.
- **Usage des lignes** (par exercice) : utilisées (`montant_engage > 0`) vs inutilisées, taux.
- **Lignes les plus sollicitées** : classement par nombre d'imputations d'engagement.
- **Lignes inutilisées** : crédit ouvert sans engagement.

### L4 — Assistant d'import nomenclature
Flux **prévisualisation → confirmation** sur `budget/import` :
- `budget.import.nomenclature.apercu` : stocke le fichier en zone temporaire (chemin en session, non
  exposé), détecte les **colonnes**, affiche les **10 premières lignes** et compte valides/rejets.
- `budget.import.nomenclature.confirmer` : import effectif puis nettoyage du fichier temporaire.
- `budget.import.nomenclature.annuler` : abandon + suppression du temporaire.
- `NomenclatureImport` enrichi : accepte les **4 sens** (recette/dépense/financement/hors-bilan, et non
  plus seulement recette/dépense), distingue **créés / mis à jour / rejetés** (avec n° de ligne + motif).
- **Rapport d'import** affiché en retour (`rapport_nomenclature`).

### L5 — NomenclaturePolicy + workflow de validation
- **NomenclaturePolicy** (`app/Policies/NomenclaturePolicy.php`) : policy unique enregistrée pour les
  4 modèles dans `AppServiceProvider` (abilities viewAny/view/create/update/delete/restore/**validate**).
  `NomenclatureController` n'utilise plus de `abort_unless` inline : toutes les actions passent par
  `$this->authorize(...)`. Le bypass administrateur reste assuré par `Gate::before`.
- **Workflow de validation** : migration additive `2026_06_25_000001` ajoute `statut`
  (`brouillon | valide`, défaut **`valide`** → nomenclature existante et imports non impactés) sur les
  4 niveaux. Une **saisie manuelle** (formulaire / assistant) naît `brouillon` ; les **imports en masse**
  restent `valide` (données de référence). Action `nomenclature.valider` (`validerElement()`) promeut
  `brouillon → valide`. Permission `nomenclature.valider` (déjà présente dans le catalogue) **accordée au
  directeur** (séparation des fonctions : le gestionnaire crée, le directeur valide). Badges de statut +
  bouton « Valider » dans l'index (tableau) et la vue détaillée.

## 8. Tests

`tests/Feature/NomenclatureTest.php` (18 tests) : index (arbre+tableau), CRUD titre complet + unicité,
blocage d'archivage si enfants, rapport PDF/Excel/CSV, non-engageabilité d'une ligne inactive,
permissions ; **L2** (écran + permission, suggestion de code, unicité, store JSON) ; **L3** (tableau de
bord, distinction utilisées/inutilisées) ; **L4** (aperçu valides/rejets, confirmation + rapport,
confirmation sans aperçu) ; **L5** (création→brouillon puis validation, séparation des fonctions
création/validation, import en masse restant validé).

## 9. Conventions & non-réalisé (volontaire)

- La journalisation et les évènements de cycle de vie sont fournis par le trait `Auditable` (l'app n'a
  pas de couche Repository/Events séparée — convention maintenue, pas de sur-ingénierie).
- Les notifications utilisent les messages flash `succes`/`erreur` du layout (pas de système Toast distinct).
- L'édition de `nature` / `date_entree_vigueur` au niveau ligne : colonnes ajoutées et exploitables ;
  leur exposition dans le formulaire Budget pourra être activée dans une itération ultérieure.
