# Module Import — documentation fonctionnelle, technique et rapport de livraison

**Version :** 1.0 · **Date :** 26 octobre 2026 · **Application :** BUDGET-CEEAC / GESBUDEP (Laravel 13, PostgreSQL 18, écrans Blade)
**Référence :** « Description fonctionnelle et technique détaillée — Module Import » v1.0

**Principe directeur :** aucune donnée n'est intégrée sans identification, rapprochement, validation, autorisation et traçabilité.

---

## 1. État initial (audit)

L'application comptait **7 imports indépendants**. Chacun avait son écran et ses règles, sans lot traçable, sans approbation et sans reprise.

| Import existant | Écran | Constat |
|---|---|---|
| Structures (`StructuresImport` / `InstitutionImportService`) | Organigramme | Écriture directe sans aperçu. **Écrase les champs** d'une structure existante : un responsable vide efface le responsable |
| Nomenclature (`NomenclatureImport`) | Budget → Import | Aperçu puis confirmation ; mise à jour silencieuse des intitulés |
| Lignes budgétaires (`LignesBudgetairesImport`) | Budget → Import | **Réécrit le montant ouvert** d'une ligne existante et **restaure les lignes supprimées** |
| PAP (`PapImport`, `PapReplacementImportService`) | PAP | Remplacement complet du PAP |
| Chaîne RBM, indicateurs | RBM → Import | Écriture directe |
| Lignes de structure budgétaire | Structure du budget | Aperçu puis confirmation |
| Relevé bancaire | Rapprochement | Hors périmètre (fonction métier propre) |

Le module Import ne supprime aucun de ces écrans : il fournit le **cycle contrôlé**, que les domaines rejoignent progressivement. Les imports historiques des quatre domaines livrés devraient être retirés des menus une fois la recette validée (voir § 9).

## 2. Ce qui est livré

| Élément | Contenu |
|---|---|
| **Socle** | Lots traçables, fichier privé et empreinte SHA-256, contrôle du format réel, correspondance des colonnes (détection automatique, ajustement, modèles réutilisables), normalisation contrôlée, validation, rapprochement, simulation, approbation par le moteur de workflow, exécution transactionnelle avec contrôle de dérive, vérification après import, rapports, journal |
| **Domaines disponibles** | P1 Référentiel organique · P1 Nomenclature budgétaire · P1 Budget annuel (lignes budgétaires) · P2 Mesures d'indicateurs (suivi-évaluation) |
| **Domaines planifiés** (affichés, non activés) | P1 PAP · P2 Utilisateurs et affectations · P2 Recettes · P3 Reprise historique EB / ENG / LIQ / ORD / PAY |
| **Écrans** | Tableau de bord et historique (`/imports`), nouvelle importation, fiche du lot (synthèse, correspondance, prévisualisation, anomalies, historique, actions, circuit) |
| **Intégration** | Workflow (circuits `IMPORT_REFERENTIEL`, `IMPORT_BUDGET`), Mes Tâches (module « Import »), Notifications, journal central (`journaux_audit`), menu Administration |

## 3. Guide utilisateur

1. **Administration → Import de données → Nouvelle importation.**
2. **Choisir le domaine.** Chaque domaine affiche ses colonnes (les obligatoires en gras) et un lien **Télécharger le modèle**.
3. **Choisir le mode :**
   - *Insertion uniquement* : seules les entités nouvelles sont créées.
   - *Insertion et mise à jour contrôlée* : en plus, les **champs autorisés** des entités existantes sont mis à jour.
   - *Simulation* : diagnostic complet, aucune intégration possible.
4. **Choisir l'exercice** (budget annuel), le fichier (Excel, CSV, JSON) et, au besoin, un modèle de correspondance, puis cliquer sur **Analyser**.
5. **Fiche du lot.** Le bandeau indique la prochaine étape.
   - **Correspondance** : associer une colonne du fichier à chaque champ ; on peut enregistrer l'association comme modèle.
   - **Prévisualisation** : opération proposée par ligne (insertion, mise à jour, identique, ignorée, conflit, erreur), avec les différences avant → après.
   - **Anomalies** : ligne, colonne, valeur, règle, gravité, message et correction suggérée. Export en Excel ou CSV.
6. **En cas d'anomalie bloquante**, corriger le fichier puis **Déposer un fichier corrigé**. Rien n'a été écrit.
7. **Lot sensible** (référentiel, nomenclature, budget) : **Soumettre à approbation**. L'approbateur reçoit une tâche dans **Mes Tâches** et une notification. Il ne peut pas être le préparateur.
8. **Exécuter l'import**, après approbation pour un lot sensible, en confirmant explicitement. L'intégration porte sur tout le lot, ou n'a pas lieu du tout. Le créateur est notifié du résultat.

**Statuts d'un lot :**

```
brouillon → fichier chargé → (correspondance) → anomalies bloquantes | simulé
          → en attente d'approbation → approuvé → en cours → terminé | échec
```

Un lot peut aussi être rejeté ou annulé. Un lot terminé, en échec, rejeté ou annulé est **immuable** : toute correction passe par un nouveau lot.

## 4. Règles par domaine

| Domaine | Clé métier | Insertion | Mise à jour (mode dédié) | Protégé, jamais modifié par import |
|---|---|---|---|---|
| Référentiel organique | code | Nouvelle structure si le type est reconnu et la hiérarchie valide ; le parent peut figurer dans le même fichier (exécuté avant l'enfant) | libellé, sigle, description, ordre, actif, responsable (si renseigné) | Type, rattachement (procédure de l'organigramme), identifiants ; **une valeur vide n'efface jamais une donnée** |
| Nomenclature | sens + titre + article + paragraphe + rubrique | Niveaux manquants créés, sans doublon | Intitulés | Codes ; aucune suppression. Avertissement si un code de rubrique existe sous un autre paragraphe (imputation ambiguë) |
| Budget annuel | exercice + code de ligne | Nouvelle ligne si l'imputation, la structure et la source existent, et le montant est ≥ 0 | Intitulé ; structure ou source **encore vides** | **Montant ouvert** (révision ou virement), imputation, structure ou source déjà renseignées ; ligne supprimée jamais restaurée. **Aucune écriture** si l'exercice est clôturé, si le budget est adopté ou en validation, ou si l'exercice est protégé (`IMPORT_EXERCICES_PROTEGES`, **2026 par défaut**) |
| Mesures d'indicateurs | indicateur + période | Nouvelle mesure au statut « saisie » (taux, feu et historique calculés par `MesureService`) | Valeur et commentaire d'une mesure non validée | Mesure validée |

**Contrôles communs à tous les domaines :**
- format réel du fichier ;
- colonnes obligatoires ;
- formats des montants, nombres, dates, booléens et courriels ;
- montants en XAF uniquement (une devise étrangère est refusée, **jamais convertie**) ;
- doublons dans le fichier ;
- références inexistantes ;
- fichier déjà importé (même empreinte) ;
- formules de tableur, signalées puis neutralisées dans les rapports.

**Gravités :**
- *Bloquante* : le lot est arrêté.
- *Majeure* : la ligne est en conflit, non intégrée ; acceptation explicite exigée à la soumission.
- *Avertissement* : information, n'empêche pas l'intégration.

## 5. Sécurité et habilitations

Toute action exige **à la fois** une permission `import.*` et le **droit métier du domaine cible** :
- `structure.importer` pour le référentiel organique ;
- `nomenclature.creer` pour la nomenclature ;
- `ligne_budgetaire.creer` pour le budget annuel ;
- `indicateur.creer` ou `suivi.creer` pour les mesures.

Le module ne peut donc pas servir de contournement.

| Permission | Proposition de rôles (`ImportPermissionsSeeder`) |
|---|---|
| `import.view`, `create`, `upload`, `map`, `validate`, `simulate`, `execute`, `cancel`, `download_report` | gestionnaire budgétaire |
| les mêmes, sans `execute`, plus `approve` et `history` | directeur |
| `view`, `approve`, `history`, `download_report` | secrétaire général |
| `view`, `history`, `download_report` | contrôleur financier, auditeur |
| toutes | administrateur |

**Séparation des fonctions :**
- **Préparation** : réservée au créateur du lot.
- **Approbation** : par l'acteur de l'étape du circuit (titulaire du rôle, `import.approve`). L'initiateur est exclu par le moteur.
- **Exécution** : `import.execute`.

**Fichiers :**
- stockage privé (`storage/app/imports`) et empreinte vérifiée à chaque lecture ; un fichier altéré est refusé ;
- contrôle de la signature binaire et du type MIME réel ; refus des exécutables et des fichiers maquillés ;
- formules jamais évaluées à la lecture ;
- export des rapports en texte explicite.

## 6. Architecture technique

| Couche | Éléments |
|---|---|
| Tables (migration `2026_10_26_200000_create_import_module_tables`, additive) | `import_lots`, `import_mappings`, `import_lignes` (préparation : données d'origine et normalisées, clé, opération, différences), `import_anomalies`, `import_evenements` (journal immuable). Les approbations passent par `workflow_instances` : aucune table redondante |
| Services (`app/Services/Import`) | `ImportLotService` (cycle et transitions), `ImportFichierService` (stockage, signature, lecture), `ImportMappingService`, `ImportAnalyseService` (normalisation, validation, rapprochement, simulation, deux passes), `ImportExecutionService` (verrou, dérive, transaction, vérification, notification), `ImportRapportService`, `ImportDomaineRegistry` |
| Domaines (`app/Services/Import/Domaines`) | Contrat `DomaineImport` ; `StructuresDomaine`, `NomenclatureDomaine`, `LignesBudgetairesDomaine`, `MesuresIndicateursDomaine` |
| Asynchrone | `ExecuterImportJob` au-delà de `IMPORT_SEUIL_ASYNCHRONE` lignes (2 000 par défaut) ; reprise `php artisan import:executer IMP-AAAA-NNNNNN` |
| Workflow | `ImportLot` implémente `PilotableParWorkflow` ; circuits `IMPORT_REFERENTIEL` (SG) et `IMPORT_BUDGET` (Directeur), seeder `ImportWorkflowSeeder`, rôles modifiables dans l'administration des circuits |
| Configuration | `config/import.php` : taille, lignes maximales, formats, seuil asynchrone, exercices protégés |

**Ajouter un domaine.** Implémenter `DomaineImport` : champs, modes, circuit, droit métier, `prevoir`, `analyser` (sans écriture), `rangExecution`, `executer`. Puis l'ajouter à `ImportDomaineRegistry::DISPONIBLES`.

## 7. Idempotence, conflits et reprise

- **Identifiants.** Chaque lot a une référence unique, et chaque fichier son empreinte SHA-256.
- **Réimport.** Un fichier réimporté donne des lignes « identiques » : aucune double écriture. Un avertissement « fichier déjà importé » signale le lot d'origine.
- **Exécution unique.** Un verrou (`lockForUpdate`) empêche deux exécutions simultanées du même lot.
- **Dérive.** Juste avant d'écrire, l'analyse est rejouée. Si la base a changé depuis la simulation, **rien n'est écrit** : le plan est recalculé, le lot revient à « simulé », et une approbation déjà donnée doit être renouvelée.
- **Atomicité.** L'exécution porte sur tout le lot en une transaction. Un échec annule tout, et un lot interrompu n'a rien écrit : il est rejoué entièrement.

## 8. Tests et recette

| Suite | Résultat |
|---|---|
| `tests/Feature/ImportModuleTest.php` (SQLite), **18 tests** : cycle complet avec approbation (tâche, notification, séparation des fonctions), réimport sans doublon, colonne manquante puis correspondance et modèle, détection par synonymes (Excel), JSON, formats, doublons, références, devise, crédit protégé, budget adopté, hiérarchie et parent dans le même fichier, structure protégée (type, responsable), mesures (non sensible, mesure validée), rejet, annulation (tâche close), dérive, fichiers maquillés ou exécutables, injection de formule, accès refusés, écrans | **18/18** |
| `tests/Recette/ImportRecetteTest.php` (PostgreSQL réel, transaction annulée) : 25 rubriques et 30 structures réimportées toutes « identiques », sans écriture ; Budget 2026 avec un crédit modifié en conflit, une ligne nouvelle bloquée, **empreinte strictement identique** (56 lignes, 48 640 795 803 XAF ouverts, 127 061 725 XAF engagés) | **3/3** |

Lancer la recette sur la base réelle (transaction annulée) : `php vendor/bin/phpunit -c phpunit-recette-pg.xml --filter ImportRecetteTest`.

**Non couvert dans cette livraison :** tests Playwright (outil non installé) et scénarios d'interruption réelle d'un job. La reprise est couverte par la conception transactionnelle et par la commande `import:executer`.

## 9. Points ouverts et recommandations

| # | Sujet | Recommandation |
|---|---|---|
| O1 | **Permissions `import.*` non attribuées** en base : seul l'administrateur utilise le module | Valider la répartition du § 5, puis `php artisan db:seed --class=ImportPermissionsSeeder` |
| O2 | Domaines PAP, utilisateurs, recettes et reprise historique de la dépense | Les implémenter sur le contrat `DomaineImport`, après arbitrage de leurs règles (en particulier : pas d'approbation fabriquée pour la reprise historique) |
| O3 | Anciens imports encore actifs (structures, nomenclature, lignes budgétaires, indicateurs), avec les défauts du § 1 | Les retirer des menus, ou les rediriger vers le module, après la recette métier |
| O4 | Protection du Budget 2026 par configuration (`IMPORT_EXERCICES_PROTEGES=2026`) | La lever uniquement par décision formelle (variable d'environnement vide ou liste ajustée) |
| O5 | Lecture Excel en mémoire (limite de 20 000 lignes et 20 Mo par lot) | Lecture en flux si des volumes supérieurs sont prévus |
| O6 | Pas d'archivage GED automatique des fichiers sources (stockage privé et empreinte uniquement) | Rattacher le fichier source et le rapport final à la GED, avec la politique de conservation |
| O7 | Interface SPA (Vue) et API REST du § 10.4 non livrées (écrans Blade uniquement) | API à ajouter en réutilisant les services (couche mince) |
