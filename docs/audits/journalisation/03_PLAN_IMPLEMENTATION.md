# 03 — Plan d'implémentation : Journalisation, Traçabilité et Horodatage

*Date : 25/10/2026. Établi à partir du rapport 01.*

## Choix d'architecture

1. **`journaux_audit` reste le journal central** (équivalent de `audit_events` du § 12.2) : il est enrichi par une migration additive, sans perte ni réécriture des 2 871 lignes existantes. Pas de table d'événements concurrente.
2. **`AuditLogger` reste le point d'entrée unique** : sa signature est conservée (73 modèles et ~98 appels inchangés) ; il est enrichi (catégorie, code, module, résultat, unité, corrélation, requête, workflow, titulaire représenté, `occurred_at` en microsecondes, empreinte SHA-256).
3. Les journaux spécialisés existants (`login_logs`, `audit_workflow`, `workflow_tache_evenements`, `ged_journaux`, `visas_electroniques`) sont **conservés** et agrégés en lecture dans l'historique d'un dossier et le journal de sécurité, au lieu d'être recopiés.
4. Les alertes réutilisent le registre `alertes` (module `audit`) ; leur historique est assuré en rendant `Alerte` auditable.
5. Les permissions du § 8 sont **résolues par alias** à partir des permissions existantes (`audit.voir`, `audit.consulter_journal`, `audit.exporter`, `admin_technique.security`), sans modifier les rôles ni exécuter de seeder.
6. Le scellement est fait **en différé** (lots d'événements chaînés), pour ne pas sérialiser toutes les écritures de l'application ; la tête de chaîne est déposée hors base et signée (HMAC).

## Lots ordonnés

| Lot | Contenu | Fichiers principaux | Dépend de | Risque | Tests |
|---|---|---|---|---|---|
| J1 | Horodatage : session PostgreSQL en UTC (`DB_TIMEZONE`), fuseau d'affichage `Africa/Libreville`, utilitaire d'affichage, contrôle de l'horloge (dérive base/application, état NTP) | `config/database.php`, `config/app.php`, `app/Support/Horodatage.php`, `HorlogeService` | — | Moyen (session SQL) | Tests horodatage + non-régression |
| J2 | Migration additive de `journaux_audit` (uuid, code, catégorie, gravité, module, résultat, motif, référence, unité, titulaire, workflow, tâche, corrélation, requête, source, `occurred_at`/`recorded_at`/`effective_at` en `TIMESTAMPTZ(6)`, empreinte) + tables `audit_scellements`, `audit_controles_integrite`, `audit_exports`, `audit_archives` + déclencheurs PostgreSQL append-only | `database/migrations/2026_10_25_200000_*` | — | Moyen (déclencheurs) | Tests modèle + recette PG |
| J3 | `AuditLogger` enrichi, `AuditContexte` (contexte de corrélation, de workflow et de délégation), middleware d'identifiant de requête, masquage récursif des secrets, garde d'immuabilité du modèle | `app/Support/AuditLogger.php`, `app/Support/AuditContexte.php`, middleware, `JournalAudit` | J2 | Moyen (toutes les écritures) | Tests unitaires |
| J4 | Intégrations : décisions de workflow (code métier, étape, autorité, seuil ORD et version, montants et reliquat PAY, délégation), rôles/profils/structures des utilisateurs (avant/après), délégations, intérims, seuil d'ordonnancement, alertes, accès refusés, échecs de connexion | `WorkflowAuditService`, `DecisionMetierService`, `UserService`, modèles, `bootstrap/app.php` | J3 | Moyen | Tests métier EB→PAY |
| J5 | Intégrité : empreinte canonique, scellement chaîné, dépôt hors base signé, vérification (empreintes, chaîne, comptage), rapport de contrôle, alerte en cas d'anomalie ; commandes `audit:sceller`, `audit:verifier`, `audit:horloge` planifiées | `app/Services/Audit/AuditIntegriteService.php`, commandes | J2, J3 | Faible | Tests intacts / altération simulée |
| J6 | Conservation : purge **désactivée par défaut** et retirée du planificateur automatique tant qu'aucune politique n'est validée ; purge réservée aux plages archivées et vérifiées, tracée ; archivage contrôlé (`audit:archiver`) sans suppression | `AuditRetentionService`, `AuditArchivageService`, `config/audit.php` | J5 | Faible | Tests archivage / purge refusée |
| J7 | Consultation et cloisonnement : `AuditAccesService` (propres événements / unité / étendu / sécurité), écrans Blade (tableau de bord, journal, fiche avec avant/après et intégrité, sécurité, historique de dossier, historique d'utilisateur, intégrité, exports CSV/JSON tracés), menu | `AuditController`, `resources/views/audit/*` | J3, J5 | Faible | Tests HTTP + captures Chrome |
| J8 | API REST en lecture `api/v1/audit/*` (événements, fiche, tableau de bord, dossier, utilisateur, sécurité, alertes, contrôles d'intégrité, exports) ; aucune création d'événement par le navigateur | `Api/V1/AuditApiController`, `routes/api.php` | J7 | Faible | Tests API |
| J9 | Tests, recette PostgreSQL (transaction annulée), non-régression, empreintes, livrables 02 et 04 à 10 | `tests/Feature/JournalisationTest.php`, `docs/audits/journalisation/` | tous | — | — |

## Opérations soumises à autorisation (non exécutées)

1. Création d'un compte PostgreSQL applicatif sans privilège de superutilisateur, avec `INSERT`/`SELECT` seuls sur les journaux (modifie les identifiants de connexion de `.env`).
2. Dépôt des scellements sur un stockage immuable externe (WORM, serveur distinct) : le dépôt se fait par défaut dans `storage/app/private/audit/scellements`.
3. Validation institutionnelle des durées de conservation par catégorie, préalable à toute purge.
4. Retrait du droit universel de l'administrateur (`Gate::before`) sur la consultation financière étendue.
5. Exécution de seeders de permissions (les permissions du § 8 sont couvertes par alias).
