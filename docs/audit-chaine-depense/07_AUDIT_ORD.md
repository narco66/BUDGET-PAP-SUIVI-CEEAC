# 07 — Audit ORD

## Conforme

- Visa unique actuel (SG), OP émis à la validation, parent LIQ.

## Absent

- Seuil versionné 5 000 000 XAF, routage Président, anti-fractionnement, création auto, snapshot de paramètre.

## Corrections réalisées

- Paramètre versionné `ordonnancement.seuil_xaf` (5 000 000, snapshot sur l’ORD).
- Routage `ORDONNANCEMENT` (SG, ≤ seuil) / `ORDONNANCEMENT_PRESIDENT`.
- Dossier PAY brouillon après signature (`etat_execution = en_preparation`).
- Délégation écrite et intérim SG / PRES : le remplaçant organique peut viser l’ORD sans porter le rôle Spatie correspondant.

## Blocage juridique

- Assiette du seuil (net par défaut **documentaire**). Flag `chaine_depense.seuil_juridiquement_valide` = false. Le routage technique peut être testé ; **déploiement production interdit** tant que le flag est faux.
