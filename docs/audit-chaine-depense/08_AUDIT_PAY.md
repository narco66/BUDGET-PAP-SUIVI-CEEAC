# 08 — Audit PAY

## Conforme

- Modes, quotas payé ≤ ordonnancé, rejets table, rapprochement bancaire.

## Incomplet

- Dual contrôle RIB : alerte + refus du visa Agent si c’est le saisisseur du nouveau RIB (pas de 3ᵉ circuit inventé).
- Preuve bancaire externe : rejet d’instrument tracé, sans portail banque.

## Absent

- Preuve d’exécution bancaire distincte du visa (hors rapprochement).

## Corrections réalisées

- Dossier PAY brouillon unique après signature ORD (`etat_execution = en_preparation` puis `execute` au visa) ; un second PAY est refusé tant que le brouillon auto couvre déjà le reliquat.
- Quota de reliquat, montant partiel API et champ UI reliquat.
- Circuit PAIEMENT à deux étapes : `comptable` puis `agent_comptable` (rôle additif, mapping `CHAINE_DEPENSE_ROLE_AGENT_COMPTABLE`).
- Policy `agir` alignée sur le rôle d’étape.
- Motif obligatoire pour un paiement partiel ; champ `rib` + alerte de changement (contrôle indépendant = visa Agent comptable).
- Clôture de chaîne persistée (`chaine_cloturee_le`) lorsque LIQ et PAY sont soldés — ce n’est pas un nouvel état juridique inventé.
- Rapprochement bancaire pose `etat_execution = rapproche` sans modifier le visa (`statut` inchangé).
- Rejet d’instrument (`etat_execution = instrument_rejete`) : contrepassation des compteurs, dossier conservé, reliquat ORD rétabli ; `chaine_cloturee_le` est levé si la chaîne n’est plus soldée (audit `chaine_depense_reouverte`).
- Création LIQ/ORD/PAY refusée si une ligne imputée est inactive ou archivée.
- Champs par mode : RIB obligatoire (virement), n° de chèque obligatoire (chèque).

## Décision

- Le rôle `agent_comptable` est créé par seeder **sans** l’attribuer aux comptes existants. L’affectation reste une décision RH.
