# BUDGET-CEEAC — Engagement (ENG)

**Commission de la CEEAC | DSI — Référentiel de conception fonctionnelle et technique**

**Version :** 1.0 — 24 octobre 2026  
**Statut :** document de spécification à valider par les responsables métiers, financiers et juridiques.

> **Portée :** description cible fondée sur les règles fonctionnelles communiquées. Les maquettes et les textes internes non annexés au présent dossier doivent être confrontés à cette version avant mise en production.

## 1. Finalité du module

Constater et sécuriser l’obligation envisagée, vérifier la régularité et la disponibilité des crédits, puis constituer le dossier de liquidation.

## 2. Entrées et préconditions

1. EB définitivement approuvée, identifiant immuable et justificatifs
2. Imputation et exercice, enveloppe autorisée et mouvements budgétaires
3. Montant engageable, tiers contractant le cas échéant, contrat/bon de commande/devis
4. Montants déjà engagés, en cours de réservation, ajustés, libérés et exécutés

## 3. Parcours métier détaillé

1. Recevoir automatiquement une EB approuvée; créer un numéro d’engagement unique avec lien parent; contrôler absence de doublon.
2. Contrôler conformité de la dépense, tiers, imputations, nature économique et pièces de commande.
3. Calculer le disponible réel de manière transactionnelle : crédits ouverts/révisés + transferts autorisés − engagements actifs nets − réservations concurrentes selon politique validée.
4. Si fonds suffisants : réserver/consommer les crédits suivant un événement comptable unique et documenté, puis soumettre à la Direction du Budget.
5. Le Directeur du Budget examine et valide ou retourne/rejette avec motifs; aucun acte d’approbation ne contourne son intervention.
6. Le Contrôleur financier examine, exerce le visa ou formule réserve/rejet; il ne doit pas être remplacé par le validateur précédent.
7. Au visa final, marquer l’engagement ferme et créer automatiquement une seule liquidation liée; les crédits ne sont jamais débités une deuxième fois à cette transition.
8. Notifier le service initiateur en charge de certifier le service fait, avec les pièces contractuelles nécessaires.

## 4. Règles de gestion et calculs

1. Un engagement ne peut excéder le crédit disponible, sauf décision de régularisation budgétaire dûment approuvée AVANT finalisation de l’engagement.
2. Aucun « crédit négatif toléré » implicite, ni seuil de dépassement caché, ni modification directe d’un budget voté.
3. Insuffisance : bloquer la validation financière, ouvrir une demande de réallocation/virement/révision/crédit complémentaire suivant textes applicables, avec pièces, acteurs habilités et piste d’audit.
4. À autorisation de redéploiement : enregistrer une opération budgétaire autonome signée, recalculer le disponible sous transaction et reprendre la validation de l’ENG.
5. Modification, réduction, annulation ou dégagement d’un engagement doivent mettre à jour le solde et interdire une réduction inférieure aux montants déjà liquidés.
6. Si plusieurs imputations : chaque ventilation doit satisfaire séparément les disponibilités et la somme des ventilations égale au montant.

## 5. Situations particulières et traitements d’exception

1. Demandes simultanées sur même ligne : verrouillage SQL ou contrôle de version évitant le sur-engagement.
2. Crédit insuffisant : statuts en_attente_arbitrage puis reprise, ou rejet formel; jamais auto-ajout de crédit.
3. Engagement antérieur partiellement liquidé : dégagement plafonné au reliquat non liquidé.
4. Prestations pluriannuelles : engagements limités par exercice/autorisations budgétaires et conventions applicables.

## 6. Documents et preuves

1. Fiche d’engagement avec référence EB, imputation, disponible avant/après, décision Budget et visa CF
2. Pièces contractuelles et acte autorisant, le cas échéant, le mouvement de crédit

## 7. Critères d’acceptation spécifiques

1. Deux validations concurrentes ne peuvent rendre négatif le disponible
2. Demande insuffisante reste bloquée jusqu’au mouvement régulier de crédits
3. ENG visé génère une seule LIQ sans double impact budgétaire

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
