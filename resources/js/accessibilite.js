/**
 * Accessibilité commune à toutes les vues (WCAG 2.2 AA) — amélioration progressive, sans effet visuel :
 * nom accessible des contrôles réduits à une icône, libellé des champs de filtre, nom des barres de progression,
 * tableaux placés dans un conteneur défilant (pas de débordement horizontal sur mobile).
 * Les vues qui fournissent déjà ces informations ne sont pas modifiées.
 */

// Libellés des icônes Bootstrap Icons employées seules dans les boutons et liens.
const ICONES = {
    'bi-eye': 'Consulter', 'bi-eye-fill': 'Consulter', 'bi-pencil': 'Modifier', 'bi-pencil-square': 'Modifier',
    'bi-trash': 'Supprimer', 'bi-trash3': 'Supprimer', 'bi-search': 'Rechercher', 'bi-x-lg': 'Réinitialiser',
    'bi-x-circle': 'Annuler', 'bi-download': 'Télécharger', 'bi-file-pdf': 'Exporter en PDF',
    'bi-filetype-pdf': 'Exporter en PDF', 'bi-file-earmark-pdf': 'Exporter en PDF', 'bi-file-earmark-excel': 'Exporter en Excel',
    'bi-filetype-xlsx': 'Exporter en Excel', 'bi-filetype-csv': 'Exporter en CSV', 'bi-file-earmark-word': 'Exporter en Word',
    'bi-printer': 'Imprimer', 'bi-box-arrow-in-right': 'Ouvrir', 'bi-box-arrow-up-right': 'Ouvrir dans un nouvel onglet',
    'bi-arrow-clockwise': 'Actualiser', 'bi-plus-lg': 'Ajouter', 'bi-plus': 'Ajouter', 'bi-plus-circle': 'Ajouter',
    'bi-archive': 'Archiver', 'bi-arrow-counterclockwise': 'Restaurer', 'bi-check-lg': 'Valider', 'bi-check2': 'Valider',
    'bi-graph-up-arrow': 'Indicateurs', 'bi-clock-history': 'Historique', 'bi-folder2-open': 'Documents',
    'bi-gear': 'Paramètres', 'bi-three-dots': 'Plus d’actions', 'bi-three-dots-vertical': 'Plus d’actions',
    'bi-envelope': 'Marquer comme non lue', 'bi-funnel': 'Filtrer', 'bi-hand-index': 'Prendre en charge',
    'bi-share': 'Partager', 'bi-upload': 'Déposer', 'bi-cloud-upload': 'Déposer', 'bi-paperclip': 'Pièces jointes',
    'bi-list': 'Menu', 'bi-bell': 'Notifications', 'bi-arrow-left': 'Retour', 'bi-chevron-left': 'Précédent',
    'bi-chevron-right': 'Suivant', 'bi-info-circle': 'Informations', 'bi-qr-code': 'Code QR', 'bi-pen': 'Signer',
};

const aUnNom = (el) => (el.textContent || '').trim() !== '' || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby')
    || (el.querySelector('img[alt]:not([alt=""])') !== null);

function nommerControlesIcones(racine) {
    racine.querySelectorAll('a[href], button').forEach((el) => {
        if (aUnNom(el)) return;
        const titre = el.getAttribute('title') || el.getAttribute('data-bs-title') || el.getAttribute('data-bs-original-title');
        let libelle = titre;
        if (!libelle) {
            const icone = el.querySelector('i[class*="bi-"]');
            const classe = icone && [...icone.classList].find((c) => ICONES[c]);
            libelle = classe ? ICONES[classe] : null;
        }
        if (libelle) {
            el.setAttribute('aria-label', libelle);
            if (!el.getAttribute('title')) el.setAttribute('title', libelle);
        }
        el.querySelectorAll('i[class*="bi-"]').forEach((i) => i.setAttribute('aria-hidden', 'true'));
    });
}

const humaniser = (nom) => (nom || '').replace(/\[\]$/, '').replace(/[_\-[\]]+/g, ' ').replace(/\bid\b/i, '').trim()
    .replace(/^./, (c) => c.toUpperCase());

function etiqueterChamps(racine) {
    racine.querySelectorAll('select, input:not([type=hidden]):not([type=submit]):not([type=button]), textarea').forEach((el) => {
        if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.closest('label')) return;
        if (el.id && racine.querySelector(`label[for="${CSS.escape(el.id)}"]`)) return;
        // Libellé visuel voisin (structure « label sans for » fréquente dans les filtres).
        const voisin = el.previousElementSibling?.tagName === 'LABEL' ? el.previousElementSibling : el.parentElement?.querySelector(':scope > label');
        if (voisin && voisin.textContent.trim()) {
            if (!el.id) el.id = 'champ-' + Math.random().toString(36).slice(2, 9);
            voisin.setAttribute('for', el.id);
            return;
        }
        const premiereOption = el.tagName === 'SELECT' ? el.options[0]?.textContent.trim() : '';
        const libelle = el.getAttribute('placeholder') || el.getAttribute('title') || premiereOption || humaniser(el.getAttribute('name'));
        if (libelle) el.setAttribute('aria-label', libelle);
    });
}

function nommerProgressions(racine) {
    racine.querySelectorAll('.progress, [role="progressbar"]').forEach((el) => {
        // L'élément porteur du rôle : la barre interne si elle l'a déjà, sinon le conteneur .progress.
        const cible = el.matches('[role="progressbar"]') ? el : (el.querySelector('[role="progressbar"]') || el);
        // Barre décorative (role=presentation / none) : rien à nommer.
        if (['presentation', 'none'].includes(cible.getAttribute('role'))) return;
        if (cible.getAttribute('aria-label') || cible.getAttribute('aria-labelledby')) return;
        const barre = cible.querySelector?.('.progress-bar') || el.querySelector('.progress-bar') || cible;
        const valeur = parseFloat(cible.getAttribute('aria-valuenow') ?? (barre?.style.width || '').replace('%', ''));
        // Un nom ARIA n'est autorisé que sur un élément de rôle progressbar (sinon « aria-prohibited-attr »).
        if (!cible.getAttribute('role')) {
            cible.setAttribute('role', 'progressbar');
            cible.setAttribute('aria-valuemin', '0');
            cible.setAttribute('aria-valuemax', '100');
            if (!Number.isNaN(valeur)) cible.setAttribute('aria-valuenow', String(Math.round(valeur)));
        }
        cible.setAttribute('aria-label', Number.isNaN(valeur) ? 'Progression' : `Progression : ${Math.round(valeur)} %`);
    });
}

function envelopperTableaux(racine) {
    racine.querySelectorAll('main table.table').forEach((t) => {
        if (t.closest('.table-responsive, .overflow-auto, .overflow-x-auto, .dropdown-menu, .modal')) return;
        const conteneur = document.createElement('div');
        conteneur.className = 'table-responsive';
        t.parentNode.insertBefore(conteneur, t);
        conteneur.appendChild(t);
    });
}

export function ameliorerAccessibilite(racine = document) {
    try {
        nommerControlesIcones(racine);
        etiqueterChamps(racine);
        nommerProgressions(racine);
        envelopperTableaux(racine);
    } catch (e) {
        console.warn('Accessibilité : amélioration partielle', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    ameliorerAccessibilite();
    // Contenus insérés après coup (panneau de notifications, détails chargés, graphiques) : même traitement.
    let attente = null;
    new MutationObserver(() => {
        clearTimeout(attente);
        attente = setTimeout(() => ameliorerAccessibilite(), 150);
    }).observe(document.body, { childList: true, subtree: true });
});
