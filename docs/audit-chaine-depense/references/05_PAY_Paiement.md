# BUDGET-CEEAC — Paiement (PAY)

**Commission de la CEEAC | DSI — Référentiel de conception fonctionnelle et technique**

**Version :** 1.0 — 24 octobre 2026  
**Statut :** document de spécification à valider par les responsables métiers, financiers et juridiques.

> **Portée :** description cible fondée sur les règles fonctionnelles communiquées. Les maquettes et les textes internes non annexés au présent dossier doivent être confrontés à cette version avant mise en production.

## 1. Finalité du module

Exécuter matériellement et comptablement les paiements autorisés, gérer les règlements partiels, prouver le décaissement et rapprocher les opérations.

## 2. Entrées et préconditions

1. ORD signé, bénéficiaire vérifié, montant net exigible
2. Coordonnées bancaires ou modalités caisse/chèque; pièces d’identité et fiscales si nécessaires
3. Disponibilités de trésorerie, règles de caisse et contrôles de l’Agence comptable
4. Paiements antérieurs, annulations, rejets bancaires et reliquat restant dû

## 3. Parcours métier détaillé

1. Importer automatiquement un ORD signé sous forme de dossier PAY avec solde initial à payer et pièces intactes.
2. Comptable/Chef comptable contrôle identité du créancier, RIB ou remise en caisse, pièces, mode de règlement, disponibilité de trésorerie et prévention des doublons.
3. Choisir caisse, chèque ou virement; saisir montant proposé, date, compte de trésorerie, pièces et identifiant d’opération.
4. Si trésorerie insuffisante : proposer un ordre de paiement partiel sur le reliquat autorisé; motif, montant partiel, calendrier indicatif et autorisation comptable.
5. Comptable/Chef comptable prépare; Agent comptable contrôle et signe l’autorisation de décaissement, sans cumul inapproprié des fonctions.
6. Au décaissement effectif, enregistrer mouvement de trésorerie, référence chèque/banque/caisse, preuve d’exécution et reçu; solde PAY recalculé transactionnellement.
7. Pour un paiement partiel, conserver ORD initial, créer autant d’échéances PAY traçables que nécessaire, chaque versement diminuant seulement le reliquat.
8. À règlement intégral, marquer PAY soldé, rapprocher l’opération avec banque/caisse puis clôturer la chaîne EB → ENG → LIQ → ORD → PAY.

## 4. Règles de gestion et calculs

1. Somme des paiements définitifs nets − remboursements/annulations régularisés ≤ net ordonnancé.
2. Paiement partiel autorisé dans la limite stricte du reliquat; ne pas créer un nouvel ORD pour chaque versement sauf exigence normative formelle.
3. Distinguer autorisation de paiement, émission de l’instrument, exécution bancaire effective et rapprochement.
4. Chèque remis n’équivaut pas nécessairement à chèque encaissé; virement initié n’équivaut pas nécessairement à virement exécuté.
5. Chaque paiement possède référence unique, statut, mode, montant, justificatif, signataire, preuve et journal de rapprochement.
6. Le rejet d’un virement ou l’opposition d’un chèque donne lieu à une contrepassation auditée, et non à un effacement.

## 5. Situations particulières et traitements d’exception

1. Paiement en trois tranches : suivi de la somme, des dates et du reliquat sans surpaiement.
2. Changement de RIB : procédure de contrôle renforcé et validation indépendante avant nouveau transfert.
3. Virement bancaire rejeté : statut échec, preuve de rejet, contrepassation, correction et nouvelle tentative identifiée.
4. Insuffisance de trésorerie : mise en attente ou paiement partiel justifié; ne modifie pas rétroactivement l’engagement budgétaire.
5. Caisse/chèque/virement : preuves et contrôles propres à chaque mode.

## 6. Documents et preuves

1. Ordre de paiement, fiche de décaissement, autorisation de paiement partiel et justificatifs
2. Reçu de paiement, avis de virement/chèque, pièces de caisse, bordereau de rapprochement

## 7. Critères d’acceptation spécifiques

1. Paiements partiels totalisent au plus le net de l’ORD
2. Paiement non exécuté ne peut être marqué payé
3. L’Agent comptable signe et les preuves restent liées au dossier

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
