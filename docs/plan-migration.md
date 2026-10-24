# Plan de migration progressive

## Principes

- Pas de bascule globale.
- Blade et SPA coexistent et appellent **les mêmes services**.
- Les routes web et les vues ne sont retirées qu’après parité vérifiée.
- Toute migration de schéma doit rester lisible par les deux interfaces.

## Étapes

| N° | Étape | Statut | Risque |
|---|---|---|---|
| 1 | État de référence (audit `docs/architecture-existante.md`) | Fait | Faible |
| 2 | Sauvegardes / restauration (commande `backup:executer` existante) | Procédure documentée ; **non exécutée ici** | Élevé si oubliée en prod |
| 3 | Socle API, conventions montants/dates, session SPA, CORS | Fait (incrément actuel) | Moyen (CSRF / CORS) |
| 4 | Extraire les règles des vues vers les services | Majoritairement déjà en `app/Services` | Faible |
| 5 | Frontend autonome (Vue) | Auth, TB, exercices, structures, budget, PAP, besoins, engagements, liquidation → paiement, recettes | Moyen |
| 6 | Premier parcours métier (soumettre / valider / rejeter) | Fait (API + SPA + tests) | Moyen |
| 7 | Modules restants (PAP, recettes, GED, workflow…) | Recettes, RBM/GAR, GED, reporting, workflow, notifications, import/export, cycle budget, exercices, structures, **utilisateurs + MFA setup** faits ; rôles/profils et PDF/Excel restent Blade | Moyen |
| 8 | Parité fonctionnelle et totaux | À faire module par module | Élevé |
| 9 | Bascule et retour arrière | Non autorisée sans décision | Élevé |
| 10 | Retrait Blade | Interdit tant que le menu SPA pointe encore vers `href_legacy` | Moyen |

## Ordre métier recommandé (dépendances)

1. Authentification, profil, navigation, exercice courant.
2. Référentiels (structures Service → Direction → Département, nomenclatures).
3. Tableau de bord (lecture, mêmes agrégats que `TableauBordService`).
4. Budgets / lignes / structure budgétaire (actions soumettre / valider / rejeter déjà exposées).
5. PAP et chaîne de résultats.
6. Expression des besoins → engagement → liquidation → ordonnancement → paiement (actions métier, idempotence, verrous).
7. Recettes, GED, reporting, workflow, administration.

## Retour arrière

- **SPA uniquement** : revenir à l’URL Blade (`SPA_LEGACY_URL`) ; aucune écriture parallèle.
- **Après migration de schéma additive** : rollback Laravel possible tant qu’aucune colonne obligatoire n’a été imposée aux clients anciens.
- **Après nouvelles écritures financières** : un rollback de schéma peut laisser des lignes orphelines ; ne pas rollbacker une migration de dépense déjà utilisée en production.

## Limites actuelles

- Génération des rapports institutionnels et téléchargements binaires (Excel/PDF/GED) restent Blade ; l’aperçu d’import structure budget est JSON.
- OpenAPI décrit le catalogue ; plusieurs schémas de réponse restent partiels.
- Pas de tests bout-en-bout navigateur automatisés dans cet incrément.
