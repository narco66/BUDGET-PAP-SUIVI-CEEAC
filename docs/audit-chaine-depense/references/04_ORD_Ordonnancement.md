# BUDGET-CEEAC — Ordonnancement (ORD)

**Commission de la CEEAC | DSI — Référentiel de conception fonctionnelle et technique**

**Version :** 1.0 — 24 octobre 2026  
**Statut :** document de spécification à valider par les responsables métiers, financiers et juridiques.

> **Portée :** description cible fondée sur les règles fonctionnelles communiquées. Les maquettes et les textes internes non annexés au présent dossier doivent être confrontés à cette version avant mise en production.

## 1. Finalité du module

Autoriser le paiement d’une dette régulièrement liquidée et transmettre l’ordre à l’Agence comptable.

## 2. Entrées et préconditions

1. Liquidation visée et exigible, identité du bénéficiaire
2. Montants brut, retenues et net; références pièces et exercice
3. Paramètre de seuil de compétence : 5 000 000 XAF, modifiable via administration autorisée
4. Historique des ordonnancements et reliquats pour éviter doublons

## 3. Parcours métier détaillé

1. Créer automatiquement l’ORD à la clôture de la LIQ visée, avec numéro unique, références et montant de référence déterminé par la règle officielle.
2. Valider exhaustivité, exigibilité, absence de paiement ou ordre en doublon, cohérence avec la LIQ.
3. Lire au moment de la soumission le seuil paramétré et sa version; conserver dans le dossier la valeur et la règle utilisées.
4. Si montant soumis à compétence ≤ 5 000 000 XAF : transmettre à la signature du Secrétaire général en qualité d’ordonnateur délégué.
5. Si montant soumis à compétence > 5 000 000 XAF : transmettre à la signature du Président en qualité d’ordonnateur.
6. Décideur compétent signe/approuve ou retourne/rejette avec motif; produire un ordre de paiement vérifiable.
7. Après signature, transmettre une fois à l’Agence comptable; créer une demande PAY prête à l’instruction comptable.

## 4. Règles de gestion et calculs

1. Seuil exprimé en XAF, conservé en paramétrage versionné; changement non rétroactif pour les ordres déjà approuvés.
2. Par défaut opérationnel de ce dossier, le montant comparé est le montant net à payer de chaque ORD; cette convention doit être validée formellement si les textes exigent le brut ou le montant cumulé d’une opération.
3. Interdire le fractionnement artificiel des ORD destiné à contourner le seuil : contrôle d’agrégation par engagement/contrat/créance et alerte d’audit.
4. Seuil exactement égal à 5 000 000 XAF : compétence SG; au-delà : Président.
5. Le signataire ne modifie pas le contenu financier d’une LIQ visée; correction via retour formel à l’étape compétente.

## 5. Situations particulières et traitements d’exception

1. Dossier à 5 000 000 XAF : route SG; à 5 000 001 XAF : route Président.
2. Changement de seuil pendant le circuit : conserver règle attachée à la soumission ou réévaluer selon politique de transition approuvée, explicitement documentée.
3. Indisponibilité du titulaire : délégation écrite, datée, bornée et enregistrée; aucune délégation tacite.
4. Doute sur fractionnement : suspension et vérification de l’opération économique globale.

## 6. Documents et preuves

1. Ordonnance/ordre de paiement signé mentionnant seuil appliqué, bénéficiaire, compte, net et pièces
2. Bordereau de transmission à l’Agence comptable et historique des signatures

## 7. Critères d’acceptation spécifiques

1. ≤ 5 millions XAF routé SG et > 5 millions routé Président
2. Signature non habilitée systématiquement interdite
3. Transfert PAY idempotent, historique et PDF cohérents

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
