# Audit seeders et factories - 2026-06-14

## Constat general

L'application dispose d'un grand nombre de seeders metier, mais les factories sont tres incompletes. Les modules RBM/GAR, PAP, budget et chaine de depense dependent surtout de seeders manuels, ce qui complique les tests unitaires et l'assemblage de donnees coherentes a la demande.

## Inventaire

- Seeders identifies : referentiels, permissions, workflow, budget, PAP, recettes, GED, suivi-evaluation, strategie, interoperabilite API, demo.
- Factories existantes avant refonte : `UserFactory`, `ExerciceFactory`, `PilierFactory`, `AxeFactory`, `IndicateurFactory`, `TypeIndicateurFactory`.
- Factories manquantes critiques : `Structure`, `Pap`, `Budget`, `Produit`, `SousProduit`, `Activite`, `Tache`, `LigneBudgetaire`, `SourceFinancement`, `ExpressionBesoin`, `Engagement`.

## Anomalies observees

- Couverture factories insuffisante pour construire une chaine complete `Pilier -> Axe -> Produit -> Sous-produit -> Activite -> Tache`.
- Libelles de plusieurs factories trop generiques (`fake()->sentence`) et peu exploitables en demonstration institutionnelle.
- Seeders demo nombreux mais peu regroupes par finalite fonctionnelle.
- Le seeder volumetrique de depense utilisait du hasard non deterministe pour le choix des dossiers et pieces ; il est acceptable pour la volumetrie, mais pas pour une demonstration reproductible.
- La base locale auditee contient des donnees referentielles et GED, mais la chaine de depense de demonstration n'etait pas chargee au moment de l'audit (`expressions_besoin=0`, `engagements=0`).

## Risques

- Tests fonctionnels difficiles a lire lorsque les objets metier doivent etre assembles manuellement.
- Risque de donnees orphelines si les factories creent des enfants sans parent RBM/PAP/budget explicite.
- Risque de divergence entre seeders et contraintes MySQL si les enums ne sont pas centralises.
- Demonstrations moins convaincantes lorsque les donnees affichent des libelles artificiels ou incoherents.

## Corrections decidees

- Ajouter des factories metier realistes, compatibles avec les migrations et les contraintes MySQL.
- Ameliorer les factories RBM existantes pour utiliser des libelles CEEAC plausibles.
- Ajouter un orchestrateur demo clair `DemoDataSeeder`, point d'entree lisible pour les donnees institutionnelles de demonstration.
- Conserver les seeders existants idempotents et non destructifs au lieu de les remplacer brutalement.

## Recommandations restantes

- Rendre le seeder bulk de depense optionnel par variable d'environnement ou commande dediee pour separer demonstration reproductible et tests de charge.
- Ajouter des tests de factories pour tous les graphes metier critiques.
- Ajouter une commande d'audit demo qui verifie les orphelins, doublons, chaines de credits, statuts workflow et couverture KPI.
