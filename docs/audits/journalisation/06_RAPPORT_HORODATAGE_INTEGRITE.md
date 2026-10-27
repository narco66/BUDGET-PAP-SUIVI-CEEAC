# 06 — Rapport horodatage et intégrité

*Date : 25/10/2026. Mesures réalisées sur la base PostgreSQL 18.4 de travail et sur l'hôte Windows de l'application.*

## 1. Configuration temporelle

| Élément | Avant | Après | Preuve |
|---|---|---|---|
| Fuseau de l'application | UTC | UTC (inchangé) | `config/app.php` |
| Fuseau de session PostgreSQL | Hérité du serveur : `Africa/Lagos` (UTC+1) | **UTC** (`DB_TIMEZONE`, défaut `UTC`) | `SHOW timezone` → `UTC` |
| Fuseau d'affichage | Aucun (UTC affiché comme heure locale) | `Africa/Libreville` (`APP_TIMEZONE_AFFICHAGE`) | `App\Support\Horodatage` |
| Colonnes temporelles du journal central | `created_at timestamp(0)` sans fuseau | + `occurred_at`, `recorded_at`, `effective_at` en **`TIMESTAMPTZ(6)`** | Migration `2026_01_02_000003` |
| Précision | Seconde | **Microseconde** (ex. `2026-10-25 06:17:19.529547+00`) | Recette PostgreSQL |
| Source de l'heure officielle | Serveur d'application | Serveur d'application uniquement ; jamais le navigateur | `AuditLogger::evenement()` : `now('UTC')` |

Vérification de l'exemple du référentiel : `2026-10-25T08:42:18.325Z` s'affiche `25/10/2026 09:42:18.325` à Libreville (test automatisé `test_affichage_dans_le_fuseau_institutionnel_sans_alterer_l_instant`).

**Écart corrigé** : avant l'intervention, les valeurs par défaut SQL (`CURRENT_TIMESTAMP`) et les requêtes brutes utilisant `now()` travaillaient en UTC+1, tandis que l'application écrivait en UTC. Les lignes historiques éventuellement écrites par une valeur par défaut SQL sous l'ancien fuseau ne sont pas réécrites (interdiction de modifier rétroactivement) ; les lignes du journal central l'ont toutes été par l'application, en UTC (`created_at` renseigné explicitement par `AuditLogger`).

**Ordre des événements** : l'identifiant `id` (séquence) fixe l'ordre d'inscription ; deux événements de la même microseconde restent ordonnés. L'instant n'est jamais utilisé comme clé de séquencement.

**Événements antérieurs au module** (2 871) : instant à la seconde (`created_at`). La colonne `recorded_at`, ajoutée avec une valeur par défaut, a reçu pour eux l'heure de la migration (25/10/2026) : elle est **masquée** à l'écran et dans l'API pour ces lignes, car elle n'a pas de valeur probante.

## 2. Synchronisation de l'horloge

Contrôle `php artisan audit:journal horloge` (également intégré à chaque vérification d'intégrité) :

| Mesure | Résultat |
|---|---|
| Écart horloge base − horloge application | **0,032 s** (seuil d'alerte : 2 s, `AUDIT_HORLOGE_ECART_MAX`) |
| Synchronisation NTP de l'hôte | **Synchronisé** — source `time.windows.com`, dernière synchronisation 25/10/2026 06:33:07 (heure locale) |
| Statut | `ok` |

Une anomalie (écart supérieur au seuil ou NTP non synchronisé) lève une alerte `HORLOGE` dans le registre des alertes et un événement `horloge_desynchronisee`, sans jamais corriger d'heure.

**Incident constaté pendant l'intervention** : le premier contrôle (06:17:59 UTC) a conclu à tort « NTP non synchronisé » : la sortie de `w32tm` est encodée en CP850 avec une espace insécable avant les deux-points, que l'analyse ne reconnaissait pas. Corrigé (détection d'encodage, espaces insécables), recontrôlé à 06:18:52 UTC : synchronisé. Ce faux positif a laissé dans la base une alerte `HORLOGE` ouverte et un événement `horloge_desynchronisee` (journal en ajout seul, non effaçable). **L'alerte est à classer par un auditeur** avec la justification « faux positif d'analyse de la sortie w32tm, corrigé le 25/10/2026 » ; elle n'a pas été classée au nom d'un compte réel.

## 3. Empreintes et chaînage

- **Empreinte d'événement** : SHA-256 de la forme canonique (`App\Support\AuditEmpreinte`) : 26 champs dans un ordre fixe, JSON trié par clé, instants normalisés `AAAA-MM-JJTHH:MM:SS.uuuuuuZ`. `user_id` est exclu (il passe légitimement à NULL par `ON DELETE SET NULL`), le nom figé `user_nom` le remplace. Calculée à l'écriture, recalculée à la vérification.
- **Chaîne** : `H_i = SHA256(H_(i-1) | id_i | empreinte_i)`, `H_0` = 64 zéros.
- **Scellement par lots** (`audit:journal sceller`, planifié toutes les heures) : les événements inscrits depuis plus de 60 s (délai de stabilisation des transactions concurrentes) sont chaînés ; la tête est signée (HMAC-SHA256, clé `AUDIT_CLE_SCELLEMENT` ou à défaut `APP_KEY`) ; un **dépôt hors base** (`storage/app/private/audit/scellements/<référence>.json`) conserve la tête, la signature et la liste `[id, empreinte]` du lot, ce qui permet de localiser précisément un événement altéré, y compris parmi les événements antérieurs au module.
- **Scellement initial réalisé** : `SCEL-20261025-061758-F86433`, événements 1 à 7032 (2 871 lignes), tête `28d524003b352bceb051d27e026c17612c6bb92e41ad7837c7e98d2e2fcf77c8`.

## 4. Contrôles d'intégrité

`audit:journal verifier` (planifié chaque jour à 05:30, déclenchable depuis l'écran « Intégrité des journaux » ou l'API par les détenteurs de `audit.integrity.execute`) vérifie : empreinte de chaque événement, nombre d'événements par lot, chaîne, signature, continuité entre lots, présence et concordance du dépôt hors base ; les événements non encore scellés sont vérifiés individuellement. Il produit un rapport horodaté (`audit_controles_integrite`) et ne réécrit jamais le journal. Après une purge réglementée, les événements retirés sont relus depuis l'archive vérifiée.

| Contrôle sur la base réelle | Résultat |
|---|---|
| `CTRL-20261025-061759-2BA9F2` | **Conforme** — 2 874 événements, 1 scellement |

| Contrôle en test (base isolée) | Résultat attendu | Résultat |
|---|---|---|
| Journal intact (REC-J16) | Conforme | Conforme |
| Altération simulée d'un événement scellé (REC-J17) | Anomalie localisée + alerte critique | Anomalie sur l'événement modifié, alerte `INTEGRITE_JOURNAL` |
| Suppression d'un événement scellé | Anomalie de comptage | Détectée |
| Décalage d'horloge simulé (REC-J19) | Alerte | Alerte `HORLOGE` + événement |
| Archivage puis purge réglementée (REC-J22) | Preuve préservée | Vérification conforme grâce à l'archive |

## 5. Protection en écriture (append-only)

| Niveau | Mécanisme | Vérification sur PostgreSQL |
|---|---|---|
| Application | Modèles `JournalAudit`, `WorkflowTacheEvenement`, scellements, contrôles, exports, archives : `updating`/`deleting` lèvent une exception | Tests JNL-006 |
| Base | Déclencheur `trg_journal_append_only` sur `journaux_audit`, `login_logs`, `audit_workflow`, `ged_journaux`, `audit_scellements`, `audit_controles_integrite`, `audit_exports`, `audit_archives` | `UPDATE` et `DELETE` refusés (SQLSTATE 42501) ; mise à NULL d'une clé étrangère (`ON DELETE SET NULL`) acceptée ; maintenance explicite (`SET LOCAL gesbudep.audit_maintenance = 'on'`) acceptée, réservée à la purge réglementée |
| Purge | Désactivée par défaut ; journal central purgeable uniquement sur plages archivées et vérifiées ; la purge se journalise | Tests REC-J22 et purge refusée sans archive |

## 6. Limites et réserves

- Le compte applicatif PostgreSQL est **superutilisateur** : il peut désactiver un déclencheur. La protection complète suppose un compte applicatif dédié (`INSERT`/`SELECT` seuls sur les journaux) — **soumis à autorisation**.
- Le dépôt des scellements est local à l'hôte : un administrateur système peut le modifier. Un dépôt externe immuable (WORM, serveur distinct) et une clé de signature conservée hors de l'hôte sont recommandés — **soumis à autorisation**.
- Les journaux spécialisés (`audit_workflow`, `login_logs`, `ged_journaux`…) sont protégés en écriture mais ne sont pas chaînés ; les décisions et accès qu'ils tracent figurent aussi au journal central chaîné.
- L'horodatage est technique (horloge serveur synchronisée NTP) et **n'est pas un horodatage électronique qualifié** ; les visas électroniques existants conservent l'empreinte SHA-256 du contenu visé.
