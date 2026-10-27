# Audit de cohérence de la chaîne de dépense — PAP → Budget → EB → ENG → LIQ → ORD → PAY

**Date :** 25 octobre 2026 · **Périmètre :** 36 EB, 32 ENG, 27 LIQ, 21 ORD, 22 PAY (exercice 2026) · **Script :** `database/scripts/audit_calculs_budgetaires.php` (section chaîne).

## 1. Contrôles réalisés, dossier par dossier

| Contrôle | Résultat initial | Résultat final |
|---|---|---|
| EB : ligne budgétaire et activité existantes | 0 anomalie | 0 |
| EB : montant estimé = Σ lignes de détail (quand il y en a) | 0 | 0 |
| ENG : EB rattachée existante ; un seul engagement par EB (index unique) | 0 | 0 |
| ENG : montant total = Σ imputations | 0 | 0 |
| ENG : Σ liquidations actives ≤ engagement | 0 | 0 |
| ENG : réservations actives = imputations (crédits réservés) | **1** (ENG-2026-0013 : 2 000 000 réservés pour 1 200 000 imputés) | **0** |
| ENG : aucune réservation active sans crédits réservés | 0 | 0 |
| LIQ : validée seulement sur engagement visé ; net = brut − retenues | 0 | 0 |
| ORD : montant ≤ liquidation (net) ; validé seulement sur LIQ validée | 0 | 0 |
| ORD : Σ paiements validés ≤ ordonnancement | 0 | 0 |
| PAY : validé seulement sur ORD validé | 0 | 0 |
| Unicité des références EB/ENG/LIQ/ORD/PAY | 0 doublon | 0 |
| Workflow : une seule instance en cours par dossier | 0 doublon | garanti par un index unique (lot Workflow) |

## 2. Anomalie corrigée

**ENG-2026-0013.** Un dégagement partiel de 800 000 XAF (journal `engagement_degage`, 24/10/2026) a réduit l'imputation et le compteur « engagé » de la ligne 37, mais pas la réservation de crédits active.

- **Code :** `EngagementService::degager` réduit désormais la réservation active du même montant, et la libère si elle est soldée.
- **Données :** la commande `depense:reconcilier-reservations` a d'abord été lancée en simulation, puis appliquée. Elle aligne la réservation sur l'imputation, sans toucher à aucun montant budgétaire. L'événement `reservation_credits_reconciliee` est journalisé avec les valeurs avant et après.
- **Test :** `AuditDonneesCorrectionsTest::test_degagement_partiel_reduit_aussi_la_reservation_active`.

## 3. Observations

- **Paiements partiels et rejets bancaires :** PAY-2026-0020 a été validé, puis son instrument a été rejeté. Son compteur a bien été annulé, mais plusieurs états le comptaient encore comme payé. Corrigé par `Paiement::regles()`.
- **Dates de paiement :** aucun des 8 paiements réglés n'a de date d'exécution saisie (`date_paiement`). Les états mensuels utilisent par défaut la date de validation, et la saisie de la date d'exécution est recommandée.
- **Hors PAP :** les EB de fonctionnement sont imputées sur leurs lignes ou sous-lignes propres (`ligne_detail_id`), sans rattachement artificiel à une activité PAP. C'est conforme.
- **Automatisation EB → ENG → LIQ :** elle est active (`chaine_depense.automatisation`). La liquidation générée au visa est soumise pour le montant total ; une liquidation partielle impose de la renvoyer en brouillon (voir le rapport Notifications).
