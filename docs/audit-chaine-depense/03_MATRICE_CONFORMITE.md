# 03 — Matrice de conformité

**Référentiel :** procédures v1.0 du 24/10/2026.  
**État existant (tableau ci-dessous) :** constat Phase A (avant corrections) — conservé comme historique.  
**État après implémentation (24/10/2026) :** voir `15_RAPPORT_FINAL_CONFORMITE.md`. Synthèse : EB/ENG/LIQ/ORD/PAY **conformes avec réserve** (mappings DRHMG/DIR-BUDGET, assiette ORD, barème fiscal, Figma, Playwright, UUID/PG non traités). SPA chaîne : cockpit, exports, vérification, dossier unifié, consolidation D1. Marchés/Contrats : API+SPA (CRUD Blade conservé).  
**Légende état :** Conforme / Partiel / Absent.  
**Criticité :** Critique (régularité financière ou contournement) / Majeur / Mineur.

## EB

| Exigence | Référence | État existant | Écart | Criticité | Correction | Test |
|---|---|---|---|---|---|---|
| EB-REQ-001 | §1 Finalité | Partiel | Pas de distinction PAP / hors PAP | Critique | Champ `categorie` + circuits | EB-T-001 |
| EB-REQ-002 | §2.1 Identité demandeur | Conforme | Demandeur, structure, exercice | Mineur | Conserver | DepenseSpaApiTest |
| EB-REQ-003 | §2.2 Catégorie + nature | Absent | Pas de catégorie ni nature imputée à l’EB | Majeur | Catégorie + imputation ligne | EB-T-001 |
| EB-REQ-004 | §2.3 Multi-sous-lignes | Absent | Un seul `montant_estime` | Critique | Table `expression_besoin_lignes` | EB-T-002 |
| EB-REQ-005 | §2.4 PJ sans forcer le fournisseur | Conforme | Pièces dossier API/SPA sans fournisseur ni `ged.creer` | Mineur | Conserver | ChaineDepenseEbConformiteTest |
| EB-REQ-006 | §2.5 Crédits cohérents | Partiel | Ligne liée via activité ; pas d’alerte disponible | Majeur | Alerte lecture seule à la soumission | EB-T-003 |
| EB-REQ-007 | §3.1 Référence unique | Conforme | `Numero::suivant` EB | Mineur | Conserver | — |
| EB-REQ-008 | §3.2 Héritage PAP lecture seule | Absent | IDs activité/tâche seulement | Critique | Snapshot `heritage_pap` | EB-T-001 |
| EB-REQ-009 | §3.3 Hors PAP sans héritage fictif | Absent | UI force une activité PAP | Critique | Circuit et saisie hors PAP | EB-T-004 |
| EB-REQ-010 | §3.4 Totaux dérivés des lignes | Absent | Montant saisi librement | Critique | Recalcul serveur | EB-T-002 |
| EB-REQ-011 | §3.5 Brouillon, soumission, instantané | Partiel | Brouillon/soumis ; pas d’instantané figé | Majeur | Snapshot à la soumission | EB-T-001 |
| EB-REQ-012 | §3.6 Circuit hors PAP DRHMG→SG→Président | Absent | Circuit unique Chef→Dir→CF | Critique | `BESOIN_HORS_PAP` ; mapping DRHMG configurable | EB-T-004 |
| EB-REQ-013 | §3.7 Circuit PAP Dir→Commissaire/SG | Absent | CF à la place du commissaire | Critique | `BESOIN_PAP` / `BESOIN_PAP_APPUI` | EB-T-001 |
| EB-REQ-014 | §3.8 Valider / retour / rejet motivé | Partiel | Workflow existant | Mineur | Étendre aux nouveaux circuits | — |
| EB-REQ-015 | §3.9 ENG auto unique | Absent | `convertir` ne crée pas l’ENG | Critique | Création idempotente au visa final | EB-T-005 |
| EB-REQ-016 | §4.1 PAP activité réelle | Partiel | Activité obligatoire aujourd’hui | Majeur | Obligatoire **si** PAP seulement | EB-T-001 |
| EB-REQ-017 | §4.2 Pas de conversion hors PAP → PAP | Absent | N/A (pas de catégorie) | Majeur | Interdire le changement après soumission | EB-T-004 |
| EB-REQ-018 | §4.3 Reprise si modification substantielle | Absent | Brouillon seulement éditable | Majeur | Retour `a_corriger` | — |
| EB-REQ-019 | §4.5 Approbation ≠ consommation crédit | Conforme | Réservation à l’ENG | — | Conserver | DepenseSpaApiTest |
| EB-REQ-020 | §5.1 Blocage PAP sans activité | Partiel | Activité required pour tous | Majeur | Cibler PAP | EB-T-001 |
| EB-REQ-021 | §5.2 Alerte insuffisance dès EB | Absent | Rien à l’EB | Majeur | Alerte non bloquante | EB-T-003 |
| EB-REQ-022 | §5.3 Annulation coordonnée ENG | Absent | Soft delete EB possible si modifiable | Critique | Bloquer si ENG lié | EB-T-005 |
| EB-REQ-023 | §6 PDF + QR | Partiel | PDF interne + QR authentifié ; Figma absent | Majeur | API SPA `besoins/{id}/pdf` | ChaineDepenseEbConformiteTest |
| EB-REQ-024 | §7.3 Un ENG malgré double clic | Absent | Doublons possibles | Critique | Unique `expression_besoin_id` | EB-T-005 |
| EB-REQ-025 | §9 SoD + périmètre | Partiel | Policy + scopes ; pas d’auto-visa explicite initiateur | Majeur | `peutAgir` par rôle d’étape | — |
| EB-REQ-026 | §11 Statuts étendus | Partiel | brouillon/soumis/valide/rejete/converti | Mineur | Ajouter `a_corriger` | — |
| EB-REQ-027 | §19 UUID | Absent | PK auto-incrément | Mineur | **Non migré** (hypothèse H2) | — |
| EB-REQ-028 | §21 Figma React | Absent | Vue 3 ; Figma introuvable | Mineur | Écart stack documenté | — |
| EB-REQ-029 | §22 Playwright | Absent | PHPUnit seulement | Majeur | PHPUnit d’abord ; Playwright plus tard | EB-T-* |

## ENG

| Exigence | Référence | État existant | Écart | Criticité | Correction | Test |
|---|---|---|---|---|---|---|
| ENG-REQ-001 | §2.1 EB approuvée | Partiel | Besoin `valide`/`converti` requis sauf `depenses.admin` | Majeur | Restreindre admin ; auto depuis EB | ENG-T-001 |
| ENG-REQ-002 | §3.1 Création auto unique | Absent | Création manuelle | Critique | Service idempotent | EB-T-005 |
| ENG-REQ-003 | §3.3 Disponible transactionnel | Conforme | `lockForUpdate` | — | Conserver | DepenseSpaApiTest |
| ENG-REQ-004 | §3.4 Soumission Direction Budget | Absent | Visa CF seul | Critique | Étape `directeur` (DIR-BUDGET) **mapping à valider** | ENG-T-002 |
| ENG-REQ-005 | §3.5–3.6 Budget puis CF distincts | Absent | Une étape | Critique | 2 étapes | ENG-T-002 |
| ENG-REQ-006 | §3.7 LIQ auto au visa | Absent | LIQ manuelle | Critique | Création unique | ENG-T-003 |
| ENG-REQ-007 | §4.1–4.2 Pas de dépassement | Conforme | Exception si insuffisant | — | Conserver | test crédits |
| ENG-REQ-008 | §4.3 Circuit réallocation | Absent | Blocage seul | Majeur | File `en_attente_arbitrage` **sans créer de crédit** | ENG-T-004 |
| ENG-REQ-009 | §4.5 Dégagement ≥ liquidé | Partiel | Annulation existante | Majeur | Plafond reliquat | — |
| ENG-REQ-010 | §4.6 Multi-imputations | Conforme | Table imputations | — | Conserver | — |
| ENG-REQ-011 | §5.1 Concurrence | Partiel | lockForUpdate ; tests 2 ENG | Mineur | Tests concurrence renforcés | ENG-T-005 |
| ENG-REQ-012 | §6 PDF | Partiel | Template interne + API SPA | Mineur | Figma absent | ChaineDepenseAvalConformiteTest |

## LIQ

| Exigence | Référence | État existant | Écart | Criticité | Correction | Test |
|---|---|---|---|---|---|---|
| LIQ-REQ-001 | §2.1 ENG visé | Conforme | Store exige parent visé | — | Conserver | DepenseAval |
| LIQ-REQ-002 | §3.1 Création auto | Absent | Manuelle | Critique | Hook visa ENG | ENG-T-003 |
| LIQ-REQ-003 | §3.2–3.4 Certification service fait | Partiel | `services_faits` existe | Majeur | Étape initiateur **avant** CF | LIQ-T-001 |
| LIQ-REQ-004 | §3.5 Visa CF distinct | Partiel | CF seul aujourd’hui | Majeur | 2 étapes | LIQ-T-001 |
| LIQ-REQ-005 | §3.6 ORD auto | Absent | Manuelle (sauf OP à l’ORD) | Critique | Hook visa LIQ | LIQ-T-002 |
| LIQ-REQ-006 | §4.1 Cumul ≤ ENG | Conforme | `DepenseQuotaService` | — | Conserver | — |
| LIQ-REQ-007 | §4.2 Partielles | Partiel | Quotas ; UI faible | Majeur | Tranches + reliquat | LIQ-T-003 |
| LIQ-REQ-008 | §4.3 Brut / retenues / net | Absent | Montant unique | Critique | Lignes + retenues **si barème configuré** ; pas inventer la fiscalité | LIQ-T-004 |
| LIQ-REQ-009 | §5.3 Doublon facture | Absent | — | Majeur | Alerte n° facture | — |
| LIQ-REQ-010 | §7.1 Visa sans service fait | Absent | Possible | Critique | Bloquer visa CF | LIQ-T-001 |

## ORD

| Exigence | Référence | État existant | Écart | Criticité | Correction | Test |
|---|---|---|---|---|---|---|
| ORD-REQ-001 | §2.1 LIQ visée | Conforme | Parent statut | — | Conserver | — |
| ORD-REQ-002 | §2.3 Seuil 5 M paramétré | Absent | Constante inexistante ; visa SG unique | Critique | Table paramètre versionnée | ORD-T-001 |
| ORD-REQ-003 | §3.1 Création auto | Absent | Manuelle | Critique | Hook LIQ | LIQ-T-002 |
| ORD-REQ-004 | §3.3–3.5 Routage SG / Président | Absent | SG seulement | Critique | Routage + version seuil stockée | ORD-T-001 |
| ORD-REQ-005 | §4.1 Non rétroactivité | Absent | — | Majeur | Snapshot seuil sur l’ORD | ORD-T-002 |
| ORD-REQ-006 | §4.2 Assiette net à payer | Bloqué | Pas de texte officiel joint | Critique | Paramètre `assiette` défaut `net` **non déployable en prod** | — |
| ORD-REQ-007 | §4.3 Anti-fractionnement | Absent | — | Critique | Alerte agrégation ENG/contrat | ORD-T-003 |
| ORD-REQ-008 | §4.4 Égalité = SG | N/A | — | Majeur | `<=` SG | ORD-T-001 |
| ORD-REQ-009 | §3.7 PAY auto | Partiel | `OrdrePaiementService::emettre` | Majeur | Dossier PAY prêt, pas « payé » | PAY-T-001 |
| ORD-REQ-010 | §5.3 Délégation écrite | Conforme avec réserve | `WorkflowInstitutionResolver` + `Delegation` | Majeur | Délégation écrite sur structure SG / PRES | ORD-T-010 |

## PAY

| Exigence | Référence | État existant | Écart | Criticité | Correction | Test |
|---|---|---|---|---|---|---|
| PAY-REQ-001 | §3.1 Import auto ORD | Partiel | OP émis ; Paiement manuel | Majeur | Créer Paiement « en préparation » | PAY-T-001 |
| PAY-REQ-002 | §3.2 Contrôle comptable | Partiel | Visa `comptable` | Majeur | Chef comptable vs Agent | PAY-T-002 |
| PAY-REQ-003 | §3.3 Modes caisse/chèque/virement | Partiel | Modes existants | Mineur | Champs par mode | — |
| PAY-REQ-004 | §3.4 Partiel trésorerie | Partiel | Quotas montant | Majeur | Reliquat + motif | PAY-T-003 |
| PAY-REQ-005 | §3.5 Agent comptable distinct | Absent | Un rôle `comptable` | Critique | Étape configurable `agent_comptable` **sans fusion de fonctions** | PAY-T-002 |
| PAY-REQ-006 | §3.6 Exécution ≠ émission | Partiel | Validation = compteur payé | Critique | Statuts émis / exécuté / rapproché | PAY-T-004 |
| PAY-REQ-007 | §4.1 Somme ≤ net ORD | Conforme | Quota | — | Conserver + concurrence | PAY-T-005 |
| PAY-REQ-008 | §4.3–4.4 Instrument vs encaissement | Absent | Un statut | Critique | Distinguer | PAY-T-004 |
| PAY-REQ-009 | §4.6 Rejet / contrepassation | Partiel | `paiement_rejets` | Majeur | Pas d’effacement | — |
| PAY-REQ-010 | §3.8 Clôture chaîne | Absent | Pas de clôture dossier | Majeur | Statut dossier unifié | PAY-T-006 |
| PAY-REQ-011 | §5.2 Changement RIB | Absent | — | Majeur | Double validation | — |

## Transversal

| Exigence | Référence | État existant | Écart | Criticité | Correction | Test |
|---|---|---|---|---|---|---|
| INT-REQ-001 | 00 chaîne auto | Absent | Transitions manuelles | Critique | Hooks idempotents | chaîne |
| INT-REQ-002 | Décimal exact | Conforme | `decimal(18,2)` + `Montant` | — | Conserver | MontantTest |
| INT-REQ-003 | PostgreSQL | Absent | SQLite/MySQL | Mineur | Pas de migration PG | — |
| INT-REQ-004 | React/Tailwind | Absent | Vue 3 / Bootstrap | Mineur | Adapter UI Vue | — |
| INT-REQ-005 | Idempotence API | Conforme | Clé + 409 | — | Étendre aux hooks auto | — |
| INT-REQ-006 | Données protégées | Conforme à ce stade | Aucune destruction Phase A | Critique | Empreintes Phase sécurisation | 14 |
