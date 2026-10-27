# Audit UI/UX global — BUDGET-CEEAC / GESBUDEP

**Date :** 26 octobre 2026 · **Environnement :** Laragon (Apache) + PostgreSQL 18 local, Vite en développement · **Outils :** Playwright 1.48 (Chrome du poste), axe-core 4.10 (WCAG 2.0 → 2.2 A/AA), analyse statique.

## 1. Architecture réelle des vues

| Élément | Constat |
|---|---|
| Interface principale | **Blade + Bootstrap 5.3 (CDN) + Bootstrap Icons 1.11**, Alpine.js, ApexCharts (Vite). 411 gabarits Blade, dont 312 gabarits de vue rendus par 347 routes GET (438 routes GET web au total, dont 69 produisant un fichier ; inventaire : `INVENTAIRE_COMPLET_VUES.md`). |
| SPA | Vue 3 + Vite (`frontend/`), consommateur de l'API v1 ; interface secondaire. |
| Référentiel visuel | **Aucune maquette exploitable dans le dépôt** : les spécifications de la chaîne de dépense citent des « maquettes Figma », mais seuls des documents texte sont présents. Le référentiel retenu est la charte en place : bleu institutionnel `#0a4a8f` / `#073564` (`config/charte_ceeac.php`, `resources/css/ceeac.css`), menu latéral sombre, cartes blanches sur fond `#f4f6f9`. |
| React, Tailwind, Font Awesome 7 | Cités par le cahier des charges, **absents de l'interface principale**. Conformément à l'interdiction de remplacer l'identité visuelle et de transformer des vues opérationnelles, aucune réécriture n'a été faite : Bootstrap et Bootstrap Icons sont conservés et harmonisés. |
| Composants communs | `components/form/*` (input, select, textarea, checkbox, file, actions, alerts), `components/navigation/sidebar-menu`, partiels de module (`_nav-module`, `_timeline`, `_pieces`, `_ged_documents`). Pas de bibliothèque DataTable : tableaux Bootstrap rendus côté serveur avec pagination Laravel. |

## 2. Constats initiaux (audit « avant », 374 vues parcourues avec le profil gestionnaire budgétaire)

| Domaine | Constat mesuré | Gravité |
|---|---|---|
| Disponibilité | 287 vues en 200, 82 en 403 (pages d'autres profils, vérifiées séparément en administrateur), 5 erreurs de navigation (délais) | — |
| **Mobile** | menu latéral empilé au-dessus du contenu (jusqu'à 60 % de la hauteur), contenu repoussé sous le pli ; débordement horizontal sur **17 vues** en 390 px (barres de filtres, groupes de boutons, navigation RBM, tableaux) | Majeure |
| Tablette | débordement sur 5 vues (768 px) : menu fixe de 260 px laissant 508 px utiles | Moyenne |
| **Contraste** (WCAG 1.4.3) | **883 nœuds sur 286 vues** : bleu Bootstrap `#0d6efd` (4,15:1 sur fond de page), textes jaunes et cyan (1,6:1 et 2:1), badges blancs sur jaune (1,6:1), gris secondaire sur fond gris (4,3:1) et sur menu sombre (2,6:1) | Majeure |
| **Noms accessibles** | liens réduits à une icône : 350 nœuds (24 vues) ; boutons : 15 ; listes déroulantes sans libellé : 181 (81 vues) ; champs sans libellé : 46 (14 vues) ; barres de progression sans nom | Critique (WCAG 4.1.2, 1.3.1) |
| Navigation clavier | pas de lien d'évitement ; anneau de focus par défaut peu visible sur les fonds de couleur | Moyenne |
| Erreurs JavaScript | `/tableaux-bord` : erreur de syntaxe (apostrophe non échappée, régression de la session précédente) qui bloquait tous ses graphiques ; antérieurement, graphiques non rendus sur 4 tableaux de bord (corrigé lors de l'audit des données) | Majeure |
| Performances | chargement médian 1,2 s, p90 1,8 s ; maximum 31 s (arbre RBM) ; cockpit 39-42 requêtes ; pilotage stratégique 72 requêtes | Majeure (RBM) |
| Cohérence visuelle | 269 styles en ligne, 260 couleurs hexadécimales en dur dans les vues ; références de dossier en rose (balise `code` par défaut) ; statuts en minuscules (« valide ») ; bleu Bootstrap et bleu institutionnel mêlés | Mineure |
| Menus | entrées menant à des 403 (GED), corrigées lors de l'audit des données | Moyenne |

## 3. Principes d'intervention

1. Corriger d'abord ce qui est **commun** (gabarit, feuille de style, module d'accessibilité) : un correctif bénéficie aux 312 vues sans risque de régression fonctionnelle.
2. Conserver la charte, les routes, les contrats d'API, les droits et les règles métier ; ne jamais remplacer une vue opérationnelle par un prototype.
3. Mesurer avant et après (même outil, mêmes vues, même profil).
4. Ne déclarer conforme que ce qui est vérifié ; classer « non vérifié » ce qui n'a pas pu l'être.
