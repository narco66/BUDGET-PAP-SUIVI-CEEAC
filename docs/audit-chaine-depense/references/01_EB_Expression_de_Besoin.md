# BUDGET-CEEAC — Expression de besoin (EB)

**Commission de la CEEAC | DSI — Référentiel de conception fonctionnelle et technique**

**Version :** 1.0 — 24 octobre 2026  
**Statut :** document de spécification à valider par les responsables métiers, financiers et juridiques.

> **Portée :** description cible fondée sur les règles fonctionnelles communiquées. Les maquettes et les textes internes non annexés au présent dossier doivent être confrontés à cette version avant mise en production.

## 1. Finalité du module

Formaliser, instruire et autoriser le besoin avant la constitution de l’engagement; distinguer impérativement PAP et hors PAP.

## 2. Entrées et préconditions

1. Identité du demandeur, position et unité initiatrice; exercice et objet précis
2. Catégorie PAP ou hors PAP; caractère investissement/fonctionnement selon imputation autorisée
3. Quantités, unités, prix estimatifs, détail multi-sous-lignes, échéance et lieu
4. Justificatifs, éventuel devis et informations de fournisseur sans forcer sa sélection précoce
5. Crédits budgétaires disponibles et imputations cohérentes

## 3. Parcours métier détaillé

1. Création : identifier exercice, service et type PAP/hors PAP, puis générer référence EB unique.
2. Si PAP : sélectionner activité programmée; hériter sans ressaisie des pilier, axe, produit, sous-produit, activité, tâches, résultats attendus, indicateurs, période, unité responsable, budget initial/révisé, engagé, exécuté et disponible.
3. Si hors PAP : rattacher à l’imputation autorisée et produire une justification métier explicite; aucun héritage PAP fictif.
4. Ajouter une ou plusieurs sous-lignes : libellé, quantité, unité, prix unitaire, taxe éventuelle, montant calculé et ventilation éventuelle; vérifier somme et devise.
5. Joindre les pièces, enregistrer en brouillon, soumettre et figer un instantané exploitable pour le circuit de validation.
6. Circuit hors PAP : initiateur (SMG/expert/chef de service) → DRHMG → Secrétaire général → approbation et signature de l’Ordonnateur (Président).
7. Circuit PAP : initiateur (expert/chef de service) → Directeur → Commissaire pour département technique ou Secrétaire général pour direction d’appui.
8. Chaque décideur peut valider, retourner pour correction ou rejeter avec motif, sans modifier silencieusement le contenu approuvé par un autre.
9. Après approbation finale : créer automatiquement, une seule fois, l’Engagement lié, avec copie figée des imputations, montants et pièces pertinentes; notifier l’acteur suivant.

## 4. Règles de gestion et calculs

1. PAP : activité existante, budget investissement compatible; champs programmatiques issus de la planification, non de champs statiques.
2. Hors PAP : voie de validation DRHMG/SG/Président; aucune conversion PAP automatique.
3. Modification d’un dossier déjà validé : reprise contrôlée du circuit si élément substantiel change.
4. EB multi-sous-lignes : le total est dérivé des lignes et non saisi arbitrairement.
5. L’approbation EB n’équivaut pas automatiquement à une consommation effective de crédit : la réservation/comptabilisation définitive intervient suivant la politique de l’Engagement; éviter double décompte.
6. Les branches PAP et hors PAP sont des règles de workflow paramétrables et testées.

## 5. Situations particulières et traitements d’exception

1. EB PAP sans activité/tâche valide : blocage de soumission.
2. Insuffisance prévisible de crédit : alerte dès l’EB et instruction via la procédure d’insuffisance à l’Engagement; pas de création de crédit artificielle.
3. Annulation après création ENG : interdite sans annulation coordonnée de l’engagement et de ses effets.

## 6. Documents et preuves

1. Fiche d’expression de besoin officielle avec numéro, demandeur, objet, sous-lignes, budget, signatures/visas et QR de vérification
2. Bordereau ou historique des validations et justificatifs

## 7. Critères d’acceptation spécifiques

1. EB PAP : héritage exact et lecture seule des données programmatiques
2. EB hors PAP : trajet DRHMG → SG → Président appliqué
3. EB approuvée produit un et un seul ENG, même après double clic ou reprise

## 8. Objet et périmètre

Le module traite une étape juridiquement et techniquement distincte de la chaîne de la dépense : de la réception du dossier entrant jusqu’à la décision et à la transmission contrôlée vers l’étape suivante. Toutes les décisions sont prises sur des données persistées, validées côté serveur et tracées. Les dossiers restent consultables dans les limites du périmètre organique et des habilitations.

## 9. Acteurs et habilitations

Chaque acteur est rattaché à une position du référentiel organique. Les droits de créer, consulter, modifier, soumettre, viser, approuver, rejeter, annuler, déléguer et exporter sont des permissions distinctes, vérifiées à chaque API. Les rôles transversaux disposent de vues élargies uniquement conformément à leur mission. Aucun acteur ne peut s’auto-attribuer un visa ni contourner une séparation des fonctions.

## 10. Données de référence obligatoires

Exploiter le budget de l’exercice, les crédits initiaux et révisés, les imputations de la nomenclature budgétaire, le référentiel organique unique, les utilisateurs actifs, les tiers/bénéficiaires, les taux et devises autorisés, les paramètres de seuils, le calendrier budgétaire et les exercices ouverts. Les changements de référentiel doivent être historisés et n’altèrent pas rétroactivement les décisions validées.

## 11. Cycle de vie commun

Statuts recommandés : brouillon, soumis, en_instruction, à_corriger, rejeté, validé/visé, approuvé, transmis, annulé, clôturé. Les transitions sont explicites et idempotentes; les motifs de retour/rejet/annulation sont obligatoires et horodatés. Un dossier rejeté n’est pas supprimé; une nouvelle soumission crée une nouvelle version ou un cycle identifié.

## 12. Éléments d’interface

Tableau de bord avec compteurs par statut, tâches à traiter, recherche et filtres serveur, liste paginée, formulaire guidé, résumé de la trajectoire budgétaire, onglet pièces jointes, historique chronologique, actions conditionnelles, aperçus de documents, validations contextualisées et notifications cliquables menant au dossier autorisé.

## 13. Conservation documentaire

La GED conserve les fichiers sources, pièces justificatives, modèles de sorties, documents générés et versions signées avec type documentaire, auteur, horodatage, empreinte SHA-256, statut, confidentialité et liens vers le dossier. Ne jamais écraser silencieusement un justificatif signé ou une version approuvée.

## 14. Contrôles et sûreté technique

Transactions PostgreSQL pour décisions et réservations financières; contraintes de cohérence, clés étrangères, index et verrouillage approprié contre les doubles validations. Les montants sont stockés en décimal exact, jamais en flottant; contrôles côté serveur; protection CSRF selon architecture, authentification et autorisations par action; journalisation non répudiable, sauvegardes et supervision.

## 15. Exceptions transversales

Prévoir dossier incomplet, pièce illisible, tiers bloqué, exercice clos, ligne budgétaire désactivée, acteur absent, dépassement de compétence, échec de génération PDF, panne de notification, dépôt en double, requêtes concurrentes et reprise après interruption. Une panne de notification ne doit pas annuler une décision financière valide; elle est mise en file de reprise.

## 16. Reporting et indicateurs

Suivre volumes entrés et sortis, stocks par étape, délais médians et maximaux, rejets et retours avec motifs, engagements et paiements par exercice, imputation, unité et projet, restes à engager/liquider/ordonner/payer, anomalies et dépassements autorisés. Toute extraction respecte le périmètre de confidentialité.

## 17. Traçabilité et audit

Journal immuable des actions sensibles : création, édition, dépôt, changement de statut, acteur et position, date serveur, anciennes et nouvelles valeurs, référence de pièce, session, empreinte documentaire et justification. Les corrections financières s’effectuent par contrepassation/ajustement documenté, jamais par suppression physique d’une décision comptabilisée.

## 18. Recette fonctionnelle minimale

Tester les parcours nominaux, rejets, retours, droits refusés, séparation des fonctions, doublons, concurrence multiutilisateur, contrôle des soldes, fermeture d’exercice, cohérence documentaire/PDF, traçabilité, pagination/filtres et notifications. Chaque test précise préconditions, opération, sortie attendue et preuve.

## 19. Modèle de données conceptuel

Entités minimales : dossiers_eb, lignes_eb, engagements, ventilations_eng, mouvements_credit, liquidations, lignes_liq, ordonnancements, paiements, tranches_paiement, pieces_jointes, validations, historiques_statuts, notifications, journal_audit. Champs transversaux : id UUID, reference, exercice_id, unite_id, initiateur_id, statut, version, timestamps, montant_decimal, devise, parent_id. Les relations parent-enfant assurent une traçabilité continue.

## 20. Contrat API indicatif

GET /api/{module} (pagination/filtres), POST /api/{module}, GET /api/{module}/{id}, PATCH /api/{module}/{id} pour brouillons, POST /api/{module}/{id}/soumettre, /retourner, /rejeter, /valider ou /viser ou /signer, GET /api/{module}/{id}/historique et /documents. Actions sensibles avec clé d’idempotence, version attendue et réponse structurée 409 en cas de conflit.

## 21. Conformité de livraison

Les interfaces React/Vite/Tailwind et les documents PDF doivent être rapprochés des maquettes Figma du module, notamment terminologie, ordre des champs, tableaux, états, totaux, emplacements de signature, QR et identités visuelles. Les maquettes originales et les textes réglementaires prévalent en cas de divergence constatée; dresser un registre des écarts à arbitrer.

## 22. Définition de terminé

Processus métier et cas d’exception implémentés; contrôles backend et PostgreSQL testés; permissions vérifiées; PDF générés et comparés au modèle; données réelles issues de la base; audit et notifications vérifiés; tests end-to-end Playwright exécutés; documentation API et manuel utilisateurs mis à jour.
