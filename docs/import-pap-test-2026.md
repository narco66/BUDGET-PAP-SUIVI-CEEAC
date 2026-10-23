# Import PAP 2026 depuis PAP_TEST.xlsx

Date d'execution : 2026-06-16

## Source

- Fichier importe : `C:\Users\HP\Downloads\PAP_TEST.xlsx`
- Modele de controle : `C:\Users\HP\Downloads\modele_detail_pap.xlsx`
- Exercice cible : 2026
- Commande appliquee : `php artisan pap:replace-from-excel "C:\Users\HP\Downloads\PAP_TEST.xlsx" --modele="C:\Users\HP\Downloads\modele_detail_pap.xlsx" --exercice=2026 --apply --json`

## Garanties appliquees

- Aucun `migrate:fresh`.
- Aucune suppression physique du PAP historique.
- Snapshot complet cree avant remplacement : `pap_historiques.id = 2`, action `archive_avant_remplacement_excel`.
- Ancienne chaine RBM archivee par soft-delete avec codes techniques `ARCH-{id}` pour liberer les contraintes uniques.
- Lignes budgetaires detachees des anciennes activites archivees.
- Cache cockpit invalide apres import.

## Resultat importe

| Niveau | Total actif |
| --- | ---: |
| Piliers | 6 |
| Axes | 21 |
| Produits | 71 |
| Sous-produits | 213 |
| Activites | 215 |
| Taches | 0 |
| Indicateurs | 0 |

Montants importes :

| Montant | XAF |
| --- | ---: |
| CEEAC | 8 270 000 000 |
| PTF | 13 988 781 000 |
| Total | 22 258 781 000 |

## Corrections automatiques

Deux codes activite dupliques dans le meme sous-produit ont ete corriges deterministiquement pour respecter les contraintes uniques :

| Ligne Excel | Code source | Code importe |
| ---: | --- | --- |
| 23 | `2012110` | `2012110-DUP2` |
| 24 | `2012120` | `2012120-DUP2` |

Les libelles depassant la capacite des champs courts ont ete tronques pour l'affichage et conserves en entier dans `description` ou `objectif_strategique`.

## Points de validation

- Audit fichier : valide, aucun ecart avec `Rapport_controle`.
- Audit PAP : 0 anomalie critique, 3 observations non bloquantes.
- Build front : OK.
- Budget : 153 activites avec montant ne sont pas liees a une ligne budgetaire, car les lignes budgetaires existantes sont des enveloppes agregees par axe (`ACT-2011`, `ACT-2012`, etc.) et non les 215 codes activite du fichier PAP.

