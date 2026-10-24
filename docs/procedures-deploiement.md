# Déploiement, sauvegarde, restauration, retour arrière

**Aucune action de production n’est autorisée par ce document seul.**

## Sauvegarde

Commande déjà planifiée : `backup:executer --type=base` (quotidien 02:00).

Avant toute bascule SPA :

1. Exécuter une sauvegarde complète (base + `storage/app` GED).
2. Vérifier qu’un fichier de backup récent existe et n’est pas vide.
3. Tester la restauration sur un clone, pas sur la production.

## Restauration

1. Arrêter les workers de file (`queue:work`) et le trafic HTTP.
2. Restaurer la base (dump MySQL ou copie du fichier SQLite).
3. Restaurer `storage/` (pièces GED).
4. `php artisan config:cache` / `route:cache` selon l’environnement.
5. Contrôler un login, un total budgétaire connu et l’ouverture d’un document GED.

## Déploiement backend

1. Déployer le code Laravel (hors `.env`).
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force` uniquement après sauvegarde vérifiée.
4. `php artisan config:cache` et `php artisan route:cache`.
5. Relancer les workers et Reverb si utilisés.

## Déploiement frontend

1. `cd frontend && npm ci && npm run build`
2. Publier `dist/` sur l’origine autorisée (HTTPS).
3. Vérifier `VITE_API_URL` au **build** (variable injectée à la compilation).
4. Vérifier CORS et `SANCTUM_STATEFUL_DOMAINS` côté backend.

## Retour arrière SPA

- Pointer le reverse-proxy / les utilisateurs vers l’UI Blade (`SPA_LEGACY_URL`).
- Inutile de rollbacker la base si seules des lectures API ont été ajoutées.

## Retour arrière schéma

- `php artisan migrate:rollback` seulement si la migration est additive et qu’aucune écriture nouvelle n’en dépend.
- Après engagements / paiements créés avec un nouveau schéma : **pas de rollback** ; corriger par migration suivante.
