# 10 — Sécurité et habilitations

## Conforme

- Sanctum session + CSRF SPA, policies, `AccessScopeService`, journal API, idempotence, `If-Match` (EB et ENG).
- SoD consécutive sur PAIEMENT (comptable ≠ Agent comptable, même si les deux rôles sont cumulés).

## Écarts

- Possession du rôle d’étape ≠ position organique + affectation au dossier.
- `depenses.admin` peut ouvrir le formulaire ENG, mais le POST exige toujours une EB validée.
- SoD : l’initiateur ne vise pas son dossier (`creator_cannot_validate`), y compris s’il cumule le rôle d’étape.
- Rôles manquants vs spec : DRHMG, Directeur du Budget, Agent comptable, Chef comptable.

## Règle

Ne pas réinitialiser les 13 rôles existants. Ajouter uniquement des **paramètres de mapping** et, si indispensable, des rôles **additifs** `updateOrCreate`.
