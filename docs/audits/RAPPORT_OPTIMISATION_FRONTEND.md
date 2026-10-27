# Rapport d'optimisation front-end — BUDGET-CEEAC / GESBUDEP

**Date :** 26 octobre 2026 · **Périmètre :** gabarit commun, feuille de style de la charte, module JavaScript commun, vues les plus lentes ou défaillantes. Pile conservée : Blade + Bootstrap 5.3 + Bootstrap Icons + Alpine.js + ApexCharts (Vite). Aucune vue n'a été remplacée par un prototype ; aucune route, aucun contrat d'API et aucun droit n'a été modifié.

## 1. Corrections communes (bénéfice pour toutes les vues)

| Fichier | Modification | Effet mesuré |
|---|---|---|
| `resources/css/ceeac.css` | **Harmonisation de la palette** : `--bs-primary` et liens = bleu institutionnel `#0a4a8f` ; boutons pleins et contour (`.btn-primary`, `.btn-outline-*`) recalés sur la charte ; variantes de texte `text-warning/info/success/danger/secondary` assombries **dans la zone de contenu** pour atteindre 4,5:1 ; badges jaunes, cyan et clairs en texte sombre ; gris secondaire du menu latéral éclairci (fond sombre). | contrastes WCAG : 883 → 134 nœuds (avant les derniers correctifs ; voir `RAPPORT_ACCESSIBILITE.md`) |
| `ceeac.css` | **Comportement mobile/tablette (< 992 px)** : menu latéral transformé en panneau escamotable (`offcanvas-lg`) ; barre supérieure qui passe à la ligne ; marges du contenu réduites ; retour à la ligne des barres de filtres, groupes de boutons, onglets et champs de formulaire (`flex-wrap`, largeur minimale des champs). Fond sombre du menu forcé en bureau (Bootstrap rend `offcanvas-lg` transparent à partir de 992 px). | vues avec défilement horizontal en 390 px : 17 → 1 (`/organigramme`, organigramme graphique, 30 px) ; en 768 px : 5 → 0 |
| `ceeac.css` | Lien d'évitement `.lien-evitement`, anneau de focus visible (`:focus-visible`) contrasté sur tous les fonds ; chiffres tabulaires dans les tableaux (`tabular-nums`, montants alignés) ; références de dossier (`code`) en couleur de la charte dans les tableaux et listes ; première lettre des badges en majuscule (« Valide » au lieu de « valide ») ; utilitaire `min-w-0` (troncature des titres longs dans la barre supérieure). | navigation clavier conforme (2.4.1, 2.4.7) ; cohérence visuelle |
| `resources/views/layouts/app.blade.php` | Lien « Aller au contenu » ; menu `<nav id="menu-principal" aria-label="Menu principal">` en `offcanvas-lg` avec bouton de fermeture mobile ; bouton « menu » dans la barre supérieure (`d-lg-none`) ; titre de page en `<h1>` tronqué ; `<main id="contenu" tabindex="-1">`. | structure de page conforme (1.3.1, 2.4.1) |
| `resources/js/accessibilite.js` (nouveau, importé par `app.js`) | Amélioration progressive **sans effet visuel** : nom accessible des boutons et liens réduits à une icône (table de 50 icônes), association des libellés voisins aux champs de filtre (ou `aria-label` depuis l'indication de saisie, la première option ou le nom du champ), rôle et nom des barres de progression, enveloppe `.table-responsive` des tableaux principaux non enveloppés ; réapplication sur les contenus insérés après coup (observateur de mutations, délai de 150 ms). Les vues qui fournissent déjà ces informations ne sont pas modifiées. | `link-name` 350 → 2, `select-name` 181 → 0, `label` 46 → 0, `aria-progressbar-name` → 0 |

## 2. Corrections ciblées

| Vue | Problème | Correction |
|---|---|---|
| `/tableaux-bord` | erreur de syntaxe JavaScript (apostrophe non échappée dans le message « aucune mesure ») qui empêchait **tous** les graphiques de la page de s'afficher | chaîne entre guillemets doubles ; état vide explicite pour les graphiques « feux » et « nature » ; conversion numérique des séries |
| `/dashboard`, `/tableaux-bord`, `/reporting/cockpit`, `/paps/dashboard` | graphiques initialisés avant le chargement d'ApexCharts (module Vite différé) : graphiques non rendus | initialisation dans `DOMContentLoaded` |
| `/risques/cartographie` | texte blanc sur cellules orange et jaune (contraste 1,9:1) | couleur du texte calculée selon le fond (`$texteCell`) |
| `/rbm/arbre/{id}` | **20,8 s** de rendu (profil auditeur) : contrôle d'autorisation `@can` répété pour chaque nœud et chaque action (des milliers d'appels) | droits RBM calculés une fois par requête (`RbmAuthorization` mémoïsé dans le conteneur, `$droitsRbm` pré-calculé dans `rbm/arbre`, partiel `_actions` sans `@can`) ; correction au passage de `$ACTION_MAP` → `self::ACTION_MAP` |
| `/reporting/cockpit` | 39 à 42 requêtes à chaque affichage ; calcul du cockpit non mis en cache | cache versionné `CockpitCache` (encodage en types natifs, mémo de requête), calcul unique ; vue réécrite (entonnoir de la chaîne, cumul, alertes cohérentes, répartition par axe juste) |
| `/strategie/dashboard/decisionnel` | 72 requêtes, synthèse d'alignement recalculée 3 fois | `StrategieAlignementService` mémoïsé ; score calculé sur les seules composantes évaluables (« non évaluable » affiché au lieu d'un 0 trompeur) |
| `/workflow/dashboard` | délais estimés, indicateurs incomplets | `WorkflowDashboardService` réécrit sur les délais réels des tâches (création → fin), SLA, retours, blocages, goulots, charge par rôle et par unité, évolution hebdomadaire ; filtres module / circuit / période |
| Toutes les vues (Apache, PHP thread-safe) | erreurs 500 intermittentes et connexions de repli vers le port 5432 sous charge (variables d'environnement partagées entre fils d'exécution) | `Env::disablePutenv()` dans `bootstrap/app.php` |

## 3. Poids des ressources (build Vite de production)

| Ressource | Avant | Après | Commentaire |
|---|---|---|---|
| `app.js` | 45,3 Ko | 49,5 Ko (17,65 Ko gzip) | + module d'accessibilité (4,2 Ko) |
| `ceeac.css` | feuille existante | 4,65 Ko | étendue : palette, mobile, focus |
| bibliothèque de graphiques | 566,9 Ko | 566,9 Ko | chargée **uniquement** sur les tableaux de bord |
| Bootstrap, Bootstrap Icons | CDN | CDN | inchangé (mis en cache par le navigateur) |

Aucune dépendance n'a été ajoutée à l'application. Playwright et axe-core ne servent qu'à l'audit et sont installés hors du dépôt.

## 4. Gains serveur mesurés

| Vue | Avant | Après |
|---|---|---|
| `/rbm/arbre/1` (auditeur) | 20,8 s | 3,0 s (1,3 s en administrateur) |
| `/reporting/cockpit` | 39-42 requêtes ; calcul du cockpit : 24 requêtes | 21 requêtes (cache chaud) ; calcul : 1 requête |
| `/strategie/dashboard/decisionnel` | 72 requêtes | 52 requêtes |

## 5. Points non traités (et pourquoi)

- **269 styles en ligne et 260 couleurs en dur** dans les gabarits : les plus visibles ont été neutralisés par la feuille commune (palette et contraste). Une réécriture systématique de plus de 300 gabarits présente un risque de régression visuelle disproportionné pour un gain faible ; elle est à mener vue par vue lors des évolutions fonctionnelles.
- **Migration vers React, Tailwind ou Font Awesome** (citée par le cahier des charges) : non réalisée. Ces technologies sont absentes de l'interface principale, et la consigne interdit de remplacer l'identité visuelle ou les vues opérationnelles.
- **`/organigramme`** (30 px de débordement en 390 px) : l'organigramme graphique est plus large que l'écran par nature ; il défile dans son conteneur. À revoir avec une vue liste dédiée au mobile si nécessaire.
