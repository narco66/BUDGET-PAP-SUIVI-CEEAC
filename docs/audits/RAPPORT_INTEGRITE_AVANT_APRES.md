# Rapport d'intégrité avant / après — BUDGET-CEEAC / GESBUDEP

**Référence « avant » :** 25 octobre 2026, 18:26 UTC (`docs/audits/donnees/empreintes_avant.json`, `empreinte_historique_workflow_avant.json`), prise avant les corrections de données et la campagne sur les vues.
**Mesure « après » :** 26 octobre 2026 (`empreintes_final.json`, `empreinte_historique_workflow_final.json`).
**Méthode :** empreinte SHA-256 déterministe de chaque catégorie, colonnes significatives triées par identifiant. Les mots de passe chiffrés entrent dans l'empreinte mais ne sont jamais affichés. Pour les tables qui ne doivent recevoir que des ajouts (journal, GED, historique des workflows), l'empreinte des N premières lignes (N = lignes de la référence) est recalculée et comparée à la référence (`prefixe.php`). Horodatages en UTC, comme en base.

## 1. Données protégées

| Catégorie | Lignes avant | Lignes après | Empreinte | Verdict |
|---|---|---|---|---|
| Utilisateurs (dont mots de passe chiffrés, statut, structure) | 17 | 17 | identique | ✅ inchangé |
| Rôles / permissions | 14 / 392 | 14 / 392 | identiques | ✅ inchangé |
| Rôles des utilisateurs, permissions des rôles, permissions directes | 17 / 1 044 / 0 | 17 / 1 044 / 0 | identiques | ✅ inchangé |
| Affectations aux structures | 13 | 13 | identique | ✅ inchangé |
| Organigramme (structures) | 78 | 78 | identique | ✅ inchangé |
| Nomenclature (titres, articles, paragraphes, rubriques) | 11 / 38 / 119 / 224 | identiques | identiques | ✅ inchangé |
| Budget 2026 (lignes) | 56 | 56 | identique | ✅ inchangé |
| Exercices | 1 | 1 | identique | ✅ inchangé |
| EB / ENG / LIQ / ORD | 36 / 32 / 27 / 21 | identiques | identiques | ✅ inchangé |
| **PAY** | 22 | 22 | **modifiée** | ⚠️ 1 dossier modifié **par un utilisateur** (§ 3) |
| **GED (documents)** | 4 | 9 | préfixe intact | ⚠️ 5 ajouts, aucun document modifié ni supprimé (§ 3) |
| **Journal d'audit** | 3 086 | 3 399 | préfixe intact | ✅ ajouts seuls (append-only) |

## 2. Historique des workflows

| Catégorie | Lignes avant | Lignes après | Verdict |
|---|---|---|---|
| Circuits / étapes des circuits | 15 / 39 | 15 / 39 | ✅ identiques |
| Instances | 122 | 123 | ✅ préfixe intact, 1 ajout |
| Décisions d'étapes | 232 | 234 | ✅ préfixe intact, 2 ajouts |
| Visas électroniques | 213 | 214 | ✅ préfixe intact, 1 ajout |
| Audit workflow | 336 | 338 | ✅ préfixe intact, 2 ajouts |
| Tâches | 220 | 222 | ✅ préfixe intact, 2 ajouts |

Aucun historique n'a été réécrit ni effacé. Les ajouts correspondent à la soumission de PAY-2026-0009 décrite ci-dessous.

## 3. Origine de chaque évolution

| Évolution | Auteur | Preuve (journal d'audit) | Commentaire |
|---|---|---|---|
| PAY-2026-0009 : brouillon → **soumis**, circuit PAIEMENT démarré (instance 537), première étape validée | **Administrateur, session Firefox** du poste (26/10, 03:08 UTC) | entrées 7689-7692, URL `/depense/paiement/83/soumettre` puis `/agir` | opération métier faite par un utilisateur dans l'interface, **pas par les outils d'audit** (qui utilisent Chrome sans interface). Rien à corriger. |
| Enveloppe budgétaire #1 : responsable modifié | Administrateur, session Firefox (26/10, 03:10 UTC) | entrées 7693-7694 | champ `responsable_id` de l'enveloppe ; les lignes et montants du Budget 2026 sont inchangés (empreinte identique). |
| GED #8 `modele_detail_pap.xlsx` | parcours d'audit des données (25/10, 18:43 UTC) | document généré par `budget.modele.pap` | téléchargement du modèle pendant le premier parcours ; les exclusions ont été élargies ensuite. **Conservé** (interdiction de supprimer un document GED). |
| GED #14 `RPT-2026-000001` | origine non déterminée (25/10, 19:18 UTC, compte administrateur) | document généré par `reporting.telecharger` | aucun outil d'audit ne s'est connecté en administrateur dans le navigateur. Probablement une action manuelle. Conservé. |
| GED #15, #18, #19 `rapport-financier-recettes-2026.docx` | **campagnes d'audit des vues** (26/10, 01:59, 02:32 et 02:50 UTC, compte gestionnaire budgétaire) | documents générés par `recettes.rapports.financier.word` | l'URL `/recettes-rapports/rapport-financier.docx` était classée « vue » par l'inventaire (gabarit Blade converti en Word). Chaque téléchargement archive automatiquement une copie en GED. **Conservés**. Inventaire corrigé : toute URI à extension de fichier est désormais classée « fichier » et exclue des parcours. |
| Journal : +313 entrées | consultations des parcours, refus d'accès tracés, connexions, opérations ci-dessus | — | comportement attendu (journal append-only, triggers PostgreSQL). |
| Réservation de ENG-2026-0013 : 2 000 000 → 1 200 000 | correction de données de l'audit (une opération, journalisée) | `depense:reconcilier-reservations --appliquer` | table `engagement_reservations`, hors des catégories protégées. Montant de l'engagement inchangé. Détail : `RAPPORT_CORRECTIONS_DONNEES.md`. |

## 4. Observation

Le téléchargement d'un document généré crée une **nouvelle copie en GED à chaque appel**, sans dédoublonnage (trois copies identiques du rapport financier des recettes). C'est le comportement voulu de l'indexation automatique (« tout document généré est archivé »), mais il produira des doublons en usage normal. Recommandation, à arbitrer : dédoublonner par empreinte SHA-256 du contenu et par route sur une courte période, ou n'archiver que les documents « officiels » (signés, diffusés). Aucune modification n'a été faite : elle touche une règle de conservation documentaire.

## 5. Conclusion

Aucune donnée protégée n'a été modifiée par les travaux d'audit et de modernisation : utilisateurs, mots de passe, rôles, permissions, affectations, organigramme, nomenclature, Budget 2026, dossiers EB → ORD. Le journal et l'historique des workflows n'ont reçu que des ajouts. Les seuls effets des outils d'audit sont 4 documents GED générés par téléchargement (#8, #15, #18, #19), conservés et documentés. La modification de PAY-2026-0009 et celle de l'enveloppe #1 sont des opérations d'un utilisateur administrateur, tracées au journal.
