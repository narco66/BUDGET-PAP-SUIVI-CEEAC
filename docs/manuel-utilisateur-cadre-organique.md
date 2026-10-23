# Manuel utilisateur - Cadre organique

## Organigramme institutionnel

Acces: menu Organigramme ou URL `/organigramme`.

Fonctions disponibles:

- rechercher une structure par code, sigle ou libelle;
- basculer entre vue arborescente et vue graphique;
- ajouter une structure racine ou une sous-structure selon les droits;
- modifier ou desactiver une structure selon les droits;
- exporter la vue en SVG;
- exporter la vue en PNG;
- imprimer en format A3 ou produire un PDF via l'impression du navigateur.

## Tableau de bord institutionnel

Acces: URL `/institution`.

Indicateurs disponibles:

- nombre de departements, directions et services;
- budget alloue par departement;
- engagement, paiement et taux d'execution par departement;
- performance budgetaire par departement;
- effectifs par departement;
- activites PAP par departement.

## Bonnes pratiques

- Rattacher chaque service a une direction.
- Rattacher chaque direction a un departement ou a une structure ayant un departement ancetre.
- Utiliser le responsable de structure pour identifier le responsable institutionnel.
- Lancer l'audit apres une modification majeure: `php artisan institution:audit-cadre`.
