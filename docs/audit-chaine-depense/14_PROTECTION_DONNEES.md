# 14 — Protection des données institutionnelles

## Mesures Phase A

- Aucun `migrate:fresh` / `db:wipe` sur une base métier.
- Aucune modification de seeders d’utilisateurs, budget 2026, nomenclature, organigramme officiel.
- Tests PHPUnit : `RefreshDatabase` **SQLite mémoire** uniquement (`phpunit.xml`).

## Empreintes

Commande `php artisan chaine-depense:empreintes` : compteurs users / rôles / structures / lignes et empreintes SHA-256 des ids utilisateurs et des noms de rôles. Aucun secret. À lancer avant/après un déploiement pour vérifier qu’aucune donnée institutionnelle n’a été réécrite. Couverture PHPUnit : `test_empreintes_chaine_depense_sans_secrets`.

## Interdictions respectées

Pas de reset de mots de passe, pas de remplacement du budget voté, pas d’UUID forcés sur les PK existantes.
