# Module GED — Gestion Électronique des Documents

## Description

Module centralisé de gestion documentaire pour la plateforme **GEST-BUDGET-PAP · CEEAC**. Il complète le socle existant `pieces_justificatives` (pièces embarquées dans les fiches métier) sans le remplacer.

Conformité visée : ISO 15489 (records management), ISO 27001 (sécurité), RGPD, préparation eIDAS.

## Architecture

```
app/
├── Enums/Ged/              # Statuts, confidentialité, signature, conservation
├── Models/Ged/             # 21 modèles Eloquent
├── Policies/Ged/           # GedDocumentPolicy
├── Services/Ged/           # Logique métier
└── Http/Controllers/Ged/   # Contrôleurs web

database/migrations/
└── 2026_06_21_000001_create_ged_module_tables.php

resources/views/ged/        # Interface Bootstrap 5
routes/web.php              # Groupe prefix ged.*
```

## Tables (21)

| Table | Rôle |
|-------|------|
| `ged_documents` | Entité centrale |
| `ged_categories` | Plan de classement — catégories |
| `ged_types_documents` | Types documentaires |
| `ged_dossiers` | Dossiers et sous-dossiers |
| `ged_versions` | Versionning (fichiers conservés) |
| `ged_archives` | Journal d'archivage |
| `ged_signatures` | Signatures électroniques internes |
| `ged_workflows` / `ged_workflow_etapes` | Circuits configurables |
| `ged_validations` | Historique validations |
| `ged_metadonnees` | Métadonnées clé/valeur |
| `ged_tags` / `ged_document_tags` | Étiquetage |
| `ged_partages` / `ged_acces` | Partage et droits fins |
| `ged_conservation_rules` | Règles de conservation |
| `ged_journaux` | Audit documentaire |
| `ged_commentaires` | Commentaires |
| `ged_favoris` | Favoris utilisateur |
| `ged_corbeille` | Suppression logique |
| `ged_numerisations` | Traçabilité numérisation |

## Relations principales

- `GedDocument` → belongsTo : categorie, typeDocument, dossier, exercice, structure, createur
- `GedDocument` → hasMany : versions, signatures, validations, archives, journaux
- `GedDocument` → morphTo : attachable (PAP, Engagement, etc.)
- `GedDocument` → belongsToMany : tags

## Permissions Spatie

Permissions de base (RoleSeeder) : `ged.voir`, `ged.creer`, `ged.modifier`, `ged.supprimer`, `ged.valider`, `ged.rejeter`, `ged.exporter`, etc.

Permissions complémentaires (GedPermissionsSeeder) :

- `ged.archiver`, `ged.restaurer`, `ged.telecharger`, `ged.previsualiser`
- `ged.partager`, `ged.signer`, `ged.publier`, `ged.rechercher`, `ged.admin`

## Stockage

- Disque : `local` → `storage/app/private/`
- Chemin : `ged/{YYYY}/{MM}/{module}/{type}/{uuid}.{ext}`
- Accès : uniquement via contrôleurs (`download`, `preview`)
- Hash SHA-256 systématique

## Codification documentaire

Format : `CEEAC/GED/{MODULE}/{TYPE}/{ANNEE}/{NUMERO}`

Exemple : `CEEAC/GED/PAP/RAPPORT/2026/000001`

## Workflows

Circuit standard (GedSeeder) : Dépôt → Contrôle → Validation → Signature → Publication → Archivage.

## Commandes autorisées

```bash
php artisan migrate
php artisan db:seed --class=GedSeeder
php artisan storage:link
php artisan test --filter=GedModuleTest
```

## Intégration modules métiers

Les documents acceptent `module_source`, `module_source_id` et relation polymorphique `attachable`. Pour afficher les documents d'un engagement :

```php
GedDocument::where('attachable_type', Engagement::class)
    ->where('attachable_id', $engagement->id)->get();
```

Le socle `HasPieces` / `PieceController` reste inchangé pour la rétrocompatibilité.

## Vérification publique

URL : `/verifier/ged/{uuid}` — contrôle d'intégrité SHA-256 et statut de signature.

---

## Phase 2 — Notifications, exports, intégration métier

### Notifications (database + mail optionnel)

Classes : `app/Notifications/Ged/*` — dépôt, validation, rejet, signature, archivage, partage, expiration, accès refusé critique.

Service : `GedNotificationService` — intégré aux services document, archive, workflow, signature, accès.

Configuration mail : `GED_NOTIFICATIONS_MAIL=true` dans `.env`

### Exports

| Route | Format | Description |
|-------|--------|-------------|
| `ged/exports/documents/excel` | Excel | Registre documentaire |
| `ged/exports/archives/excel` | Excel | Registre des archives |
| `ged/exports/signatures/excel` | Excel | Registre des signatures |
| `ged/exports/journal/excel` | Excel | Journal documentaire |
| `ged/exports/documents/{id}/fiche` | PDF | Fiche documentaire |
| `ged/exports/documents/{id}/certificat-integrite` | PDF | Certificat SHA-256 |
| `ged/exports/documents/{id}/certificat-signature` | PDF | Certificat de signature |
| `ged/exports/documents/{id}/bordereau-depot` | PDF | Bordereau de dépôt |
| `ged/exports/documents/{id}/bordereau-archivage` | PDF | Bordereau d'archivage |

### Widget documents sur fiches métier

Partial : `resources/views/partials/_ged_documents.blade.php`

Intégré sur : engagements, besoins, dépenses (liquidation/ordonnancement/paiement), PAP.

Route dépôt : `POST ged/attach/{type}/{id}`

### OCR & antivirus

- `config/ged.php` — paramètres centralisés
- `GedOcrService` — extraction basique (txt/csv) ou Tesseract si `GED_OCR_ACTIF=true`
- `GedAntivirusService` — hook ClamAV si `GED_ANTIVIRUS_ACTIF=true`

### Signature eIDAS (extensible)

- `GedSignatureProviderInterface`
- `InternalSignatureProvider` (production)
- `ExternalSignatureProviderStub` — `GED_SIGNATURE_PROVIDER=externe`

### Commande planifiable

```bash
php artisan ged:expiration-alerte --jours=30
```

Marque les documents expirés et envoie les alertes de conservation.

### Variables `.env` recommandées

```env
GED_NOTIFICATIONS_MAIL=false
GED_EXPIRATION_ALERTE_JOURS=30
GED_OCR_ACTIF=false
GED_ANTIVIRUS_ACTIF=false
GED_SIGNATURE_PROVIDER=interne
```

---

## Phase 3 — Planification, intégrations étendues, eIDAS

### Scheduler Laravel (`bootstrap/app.php`)

| Commande | Fréquence | Rôle |
|----------|-----------|------|
| `ged:expiration-alerte` | Quotidien 07:00 | Alertes expiration |
| `ged:ocr-traiter` | Quotidien 02:30 | Indexation OCR batch |
| `ged:conservation-appliquer` | Lundi 06:00 | Archivage auto selon règles |

Sur Laragon : planifier `php artisan schedule:run` chaque minute (Planificateur de tâches Windows).

### Trait `HasGedDocuments`

Ajouté sur les modèles métier (Marche, Contrat, Recommandation, Formation, Suivi, Collecte, Rapport, etc.) pour relation `gedDocuments()`.

### Modules intégrés (widgets)

| Module | Vue | Alias attach |
|--------|-----|--------------|
| Marchés | `marches/show` | `marche` |
| Contrats | `contrats/show` | `contrat` |
| Recommandations | `recommandations/show` | `recommandation` |
| Formations | `formations/show` | `formation` |
| Suivi | `suivi/show` | `suivi` |
| Reporting | `reporting/historique` | indexation bouton |
| RBM | `rbm/hub` | lien recherche GED |

### Pont Reporting ↔ GED

`GedRapportBridgeService::indexer()` — route `POST ged/rapports/{rapport}/indexer`

### Recherches sauvegardées

Table `ged_recherches_sauvegardees` — sauvegarde/chargement depuis la page recherche avancée.

### Signature externe eIDAS-ready

`ExternalSignatureProvider` — API REST configurable, fallback automatique vers signature interne si indisponible.

## Stratégie sauvegarde

1. Sauvegarder la base MySQL (tables `ged_*`)
2. Sauvegarder `storage/app/private/ged/`
3. Exporter les journaux `ged_journaux` pour audit

## Fichiers modifiés (intégration)

| Fichier | Modification |
|---------|--------------|
| `routes/web.php` | Routes GED + vérification publique |
| `resources/views/layouts/app.blade.php` | Menu GED (18 entrées) |
| `app/Providers/AppServiceProvider.php` | Policy GedDocument |
| `database/seeders/SafeDatabaseSeeder.php` | GedPermissionsSeeder + GedSeeder |

## Fichiers non modifiés (préservés)

- `PieceController`, `pieces_*` routes, `_pieces.blade.php`
- Migration `pieces_justificatives`
- Tous les modules Budget, PAP, Suivi, Reporting existants
