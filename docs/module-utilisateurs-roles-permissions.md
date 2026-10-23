# Module — Utilisateurs, Rôles, Permissions & Profils

> GEST-BUDGET-PAP · Commission de la CEEAC — Gestion des accès (RBAC + profils + périmètres)

## 1. Objectif
Fournir un système complet, sécurisé et évolutif de gestion des accès : utilisateurs rattachés à
l'organisation (structure, fonction, poste), rôles et permissions fins (Spatie), profils fonctionnels
regroupant des rôles, périmètres d'accès aux données, et journalisation complète — dans le respect
de la séparation des fonctions (ordonnateur / contrôleur financier / comptable / auditeur / administrateur)
et du moindre privilège.

## 2. Architecture
- **RBAC** : `spatie/laravel-permission` (rôles ↔ permissions ↔ utilisateurs).
- **Bypass administrateur** : `Gate::before` (rôle `administrateur` = tous droits).
- **Services** : `UserService` (cycle de vie, mots de passe, affectations sécurisées),
  `AccessScopeService` (filtrage des données par périmètre).
- **Policies** : `UserPolicy`, `RolePolicy` (enregistrée explicitement car modèle Spatie hors `App\Models`),
  `ProfilPolicy`.
- **Middleware** : `ForcePasswordChange` (web, global) force le renouvellement à la 1ʳᵉ connexion / après reset.
- **Audit** : trait `Auditable` + `AuditLogger` (auteur, date, action, avant/après, IP, navigateur, URL) ;
  `login_logs` pour les connexions.

## 3. Tables
| Table | Rôle |
|---|---|
| `users` (étendue) | + `avatar, statut(actif/suspendu/desactive), doit_changer_mot_de_passe, poste_id, derniere_connexion_at`, soft deletes |
| `roles` (étendue) | + `type(systeme/metier), protege, libelle, description` |
| `permissions` (étendue) | + `module, action, libelle` |
| `model_has_roles`, `model_has_permissions`, `role_has_permissions` | pivots Spatie |
| `postes` | postes (liés optionnellement à une fonction) |
| `profils`, `profil_role`, `user_profils` | profils fonctionnels ↔ rôles ↔ utilisateurs |
| `user_structures` | rattachement multi-structures (principal = `users.structure_id`) |
| `user_access_scopes` | périmètres d'accès (global/département/direction/service/programme/exercice/lecture_seule/propres_dossiers/a_valider) |
| `login_logs` | connexions réussies/échouées (IP, navigateur, motif) |
| `password_histories` | derniers mots de passe (anti-réutilisation, N=5) |

## 4. Relations clés
- `User` N–N `Role` (Spatie), N–N `Profil` (`user_profils`), N–N `Structure` (`user_structures`),
  1–N `UserAccessScope`, 1–N `LoginLog`, 1–N `PasswordHistory`, N–1 `Structure`/`Fonction`/`Poste`.
- `Profil` N–N `Role` (`profil_role`). Affecter un profil fusionne ses rôles dans l'utilisateur.
- `Role` N–N `Permission` ; `Permission` portée par `module.action`.

## 5. Rôles par défaut (13)
`administrateur` (système, protégé), `president`*, `vice_president`*, `secretaire_general`*,
`commissaire`, `directeur`, `chef_service`, `gestionnaire_budgetaire`, `controleur_financier`*,
`comptable`*, `auditeur`*, `point_focal`, `lecteur`. (* = protégé / sensible)

## 6. Permissions par module
20 modules × 10 actions (`voir, creer, modifier, supprimer, valider, rejeter, exporter, importer,
generer_pdf, consulter_journal`) = 200 permissions. Modules : structure, utilisateur, role, profil,
rbm, pap, nomenclature, ligne_budgetaire, besoin, engagement, liquidation, ordonnancement, paiement,
indicateur, suivi, ged, rapport, tableau_bord, audit, parametre. Convention : `module.action`.

## 7. Règles de sécurité
- Seul un **administrateur** attribue un rôle **protégé** (dont `administrateur`).
- **Anti auto-escalade** : nul ne s'attribue un rôle protégé qu'il ne détient pas déjà.
- **Rôles protégés / système non supprimables** (policy + garde contrôleur) ; **rôle utilisé non supprimable**.
- **Moindre privilège** : refus silencieux des rôles non autorisés lors de la synchronisation.
- **Séparation des fonctions** matérialisée par les rôles distincts et les circuits de workflow.
- **Mots de passe** : changement obligatoire à la 1ʳᵉ connexion / après reset, historique anti-réutilisation,
  politique `min(8) + mixedCase + numbers`.
- **Traçabilité** complète des actions sensibles + journal des connexions.

## 8. Commandes artisan
```bash
php artisan migrate                 # applique les migrations du module
php artisan storage:link            # expose les avatars (storage/app/public)
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=ProfilSeeder
php artisan db:seed --class=PosteSeeder
# ou reconstruction complète :
php artisan migrate:fresh --seed
```

## 9. Tests
```bash
php artisan test --filter=AccessModuleTest
php artisan test --filter=ForcePasswordChangeTest
```
Couvre : création utilisateur (+ force MDP + audit), attribution rôle/permission, restriction d'accès,
accès par structure (descendants), rôle protégé / utilisé non supprimable, anti-escalade,
réutilisation MDP, journalisation des connexions, filtrage des menus.

## 10. Procédure d'utilisation
1. **Utilisateurs › Nouvel utilisateur** : renseigner identité, structure/fonction/poste, statut,
   rôles et profils ; un mot de passe temporaire est généré (laisser vide) → l'agent le change à sa 1ʳᵉ connexion.
2. **Périmètres** (fiche utilisateur) : définir l'étendue d'accès aux données.
3. **Rôles & permissions** : créer/éditer un rôle et cocher ses permissions dans la matrice ; consulter
   la **matrice rôles/permissions** en synthèse.
4. **Profils** : regrouper des rôles pour une affectation rapide.
5. **Journal des connexions** : superviser les accès ; le journal d'activité global est dans **Audit**.

## 11. Intégration
Le module s'appuie sur les référentiels existants (structures, programmes, exercices) et alimente
tous les modules métier via les permissions `module.action`, les Policies et le filtrage `AccessScopeService`.
Aucune donnée existante n'est détruite : extensions additives, soft deletes, contraintes et index.
