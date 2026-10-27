<?php

namespace App\Services\Import\Domaines;

use App\Models\ImportLigne;
use App\Models\Structure;
use App\Models\User;
use App\Services\Import\Support\AnalyseLigne;
use App\Services\Import\Support\ChampImport;
use App\Services\Import\Support\ContexteImport;
use App\Services\Institution\StructureHierarchieService;
use App\Support\ReferentielAdministratifAuthorization;
use Illuminate\Database\Eloquent\Model;

/**
 * Référentiel organique (P1). Les structures existantes sont reconnues par leur code et protégées :
 * identifiants conservés, type et rattachement jamais modifiés par import (procédure de l'organigramme),
 * responsable jamais effacé ; seuls les champs descriptifs sont mis à jour, en mode « mise à jour ».
 */
class StructuresDomaine implements DomaineImport
{
    /** Ordre d'exécution : les niveaux supérieurs avant leurs enfants. */
    private const RANGS = [
        'presidence' => 1, 'vice_presidence' => 2, 'secretariat_general' => 3, 'departement' => 4,
        'commissariat' => 5, 'direction' => 6, 'cellule' => 7, 'service' => 8,
    ];

    /** Champs modifiables d'une structure existante (mode « mise à jour »). */
    private const MODIFIABLES = ['libelle', 'sigle', 'description', 'ordre', 'actif', 'responsable_user_id'];

    public function code(): string
    {
        return 'structures';
    }

    public function libelle(): string
    {
        return 'Référentiel organique (structures)';
    }

    public function description(): string
    {
        return 'Organes, départements, directions, services et cellules, avec leur rattachement hiérarchique.';
    }

    public function champs(): array
    {
        return [
            new ChampImport('code', 'Code de la structure', true, 'code', ['code_org', 'code_organique', 'code_structure']),
            new ChampImport('libelle', 'Libellé', true, 'texte', ['intitule', 'nom', 'structure', 'designation']),
            new ChampImport('type', 'Type', true, 'texte', ['type_structure', 'niveau_organique'], 'presidence, vice_presidence, secretariat_general, departement, commissariat, direction, cellule, service'),
            new ChampImport('sigle', 'Sigle', false, 'texte'),
            new ChampImport('parent_code', 'Code de la structure parente', false, 'code', ['parent', 'code_parent', 'rattachement']),
            new ChampImport('responsable_email', 'Courriel du responsable', false, 'email', ['responsable', 'email_responsable']),
            new ChampImport('description', 'Description', false, 'texte'),
            new ChampImport('actif', 'Active (oui/non)', false, 'booleen', ['statut', 'active']),
            new ChampImport('ordre', 'Ordre d\'affichage', false, 'entier'),
        ];
    }

    public function exigeExercice(): bool
    {
        return false;
    }

    public function modes(): array
    {
        return ['insertion', 'mise_a_jour', 'simulation'];
    }

    public function circuit(): ?string
    {
        return 'IMPORT_REFERENTIEL';
    }

    public function autorise(User $user): bool
    {
        return ReferentielAdministratifAuthorization::peut($user, 'importer');
    }

    public function exemple(): array
    {
        return [
            ['code' => 'DIR-EXEMPLE', 'libelle' => 'Direction de l\'exemple', 'type' => 'direction', 'sigle' => 'DEX', 'parent_code' => 'SG', 'responsable_email' => '', 'description' => '', 'actif' => 'oui', 'ordre' => 1],
            ['code' => 'SVC-EXEMPLE', 'libelle' => 'Service de l\'exemple', 'type' => 'service', 'sigle' => '', 'parent_code' => 'DIR-EXEMPLE', 'responsable_email' => '', 'description' => '', 'actif' => 'oui', 'ordre' => 1],
        ];
    }

    public function preparer(ContexteImport $ctx): void
    {
        $ctx->cache['structures'] = Structure::query()->get()->keyBy('code');
        $ctx->cache['types'] = StructureHierarchieService::types();
    }

    public function prevoir(array $lignes, ContexteImport $ctx): void
    {
        foreach ($lignes as $d) {
            if (! empty($d['code']) && ! empty($d['type']) && ! isset($ctx->cache['structures'][$d['code']])) {
                $ctx->prevoir('structure', $d['code'], ['type' => mb_strtolower((string) $d['type'])]);
            }
        }
    }

    public function analyser(AnalyseLigne $ligne, int $numero, ContexteImport $ctx): void
    {
        $d = $ligne->donnees;
        $ligne->cle = $d['code'] ?? null;
        $type = mb_strtolower((string) ($d['type'] ?? ''));
        $ligne->donnees['type'] = $type;

        if (! in_array($type, $ctx->cache['types'], true)) {
            $ligne->bloquante('type_inconnu', "Type « {$type} » inconnu.", 'type', $d['type'] ?? null, 'Types admis : '.implode(', ', $ctx->cache['types']).'.');
        }

        // Parent : existant en base, ou prévu dans le même lot (parents avant enfants à l'exécution).
        $typeParent = null;
        $parentExistant = null;
        if (! empty($d['parent_code'])) {
            $parentExistant = $ctx->cache['structures'][$d['parent_code']] ?? null;
            $prevu = $ctx->prevue('structure', $d['parent_code']);
            if ($parentExistant) {
                $typeParent = $parentExistant->type;
            } elseif ($prevu) {
                $typeParent = $prevu['type'];
            } else {
                $ligne->bloquante('reference_inexistante', "Structure parente « {$d['parent_code']} » introuvable (ni en base ni dans le fichier).", 'parent_code', $d['parent_code']);
            }
        }
        // Hiérarchie contrôlée pour une création ; une structure existante n'est jamais déplacée ni retypée par import.
        $dejaConnue = isset($ctx->cache['structures'][$ligne->cle]);
        if (! $dejaConnue && $ligne->aDesBloquantes() === false && ! StructureHierarchieService::parentAutorise($type, $typeParent)) {
            $ligne->bloquante('hierarchie', $typeParent === null
                ? "Une structure de type « {$type} » doit avoir une structure parente."
                : "Une structure de type « {$type} » ne peut pas être rattachée à un(e) « {$typeParent} ».", 'parent_code', $d['parent_code'] ?? null);
        }

        $responsableId = null;
        if (! empty($d['responsable_email'])) {
            $responsableId = User::query()->where('email', $d['responsable_email'])->value('id');
            if (! $responsableId) {
                $ligne->bloquante('reference_inexistante', "Responsable « {$d['responsable_email']} » : aucun compte avec ce courriel.", 'responsable_email', $d['responsable_email'], 'Créer d\'abord le compte (procédure d\'habilitation), sans import.');
            }
        }
        $ligne->donnees['responsable_user_id'] = $responsableId;

        /** @var Structure|null $existante */
        $existante = $ctx->cache['structures'][$ligne->cle] ?? null;
        if (! $existante) {
            $ligne->operation = 'inserer';
            if ($ligne->cle && $type !== '') {
                $ctx->prevoir('structure', $ligne->cle, ['type' => $type]);
            }

            return;
        }

        $ligne->cible = $existante;
        // Type et rattachement : jamais modifiés par import (procédure dédiée de l'organigramme).
        if ($type !== '' && $type !== $existante->type) {
            $ligne->majeure('modification_protegee', "La structure existe avec le type « {$existante->type} » : le changement de type ne se fait pas par import.", 'type', $type, 'Utiliser l\'organigramme (procédure tracée).');
        }
        $parentActuel = $existante->parent?->code;
        if (array_key_exists('parent_code', $d) && $d['parent_code'] !== null && $d['parent_code'] !== $parentActuel) {
            $ligne->majeure('modification_protegee', 'Changement de rattachement (« '.($parentActuel ?? 'aucun').' » → « '.$d['parent_code'].' ») : non réalisé par import.', 'parent_code', $d['parent_code'], 'Utiliser l\'organigramme (procédure tracée).');
        }

        foreach (self::MODIFIABLES as $champ) {
            if (! array_key_exists($champ, $ligne->donnees) || $ligne->donnees[$champ] === null) {
                continue;   // valeur absente : jamais d'effacement d'une donnée existante
            }
            $avant = $existante->{$champ};
            $apres = $ligne->donnees[$champ];
            if ((string) $avant !== (string) ($champ === 'actif' ? (int) $apres : $apres) && ! ($champ === 'actif' && (bool) $avant === (bool) $apres)) {
                $ligne->difference($champ, $avant, $apres);
            }
        }

        if ($ligne->aDesMajeures()) {
            $ligne->operation = 'conflit';
        } elseif ($ligne->differences === []) {
            $ligne->operation = 'identique';
        } elseif ($ctx->miseAJourAutorisee()) {
            $ligne->operation = 'mettre_a_jour';
        } else {
            $ligne->operation = 'ignorer';
            $ligne->avertissement('existe_deja', 'Structure existante, différente du fichier : non modifiée en mode « insertion ».', 'code', $ligne->cle, 'Choisir le mode « mise à jour contrôlée » pour appliquer les champs descriptifs.');
        }
    }

    public function rangExecution(ImportLigne $ligne): int
    {
        return self::RANGS[$ligne->donnees['type'] ?? ''] ?? 99;
    }

    public function executer(ImportLigne $ligne, ContexteImport $ctx): ?Model
    {
        $d = $ligne->donnees;
        if ($ligne->operation === 'mettre_a_jour') {
            $structure = Structure::query()->findOrFail($ligne->cible_id);
            $structure->update(array_intersect_key(
                array_map(fn ($v) => $v['apres'], $ligne->differences ?? []),
                array_flip(self::MODIFIABLES),
            ));

            return $structure;
        }

        $parent = ! empty($d['parent_code']) ? Structure::query()->where('code', $d['parent_code'])->firstOrFail() : null;

        return Structure::create([
            'code' => $d['code'],
            'libelle' => $d['libelle'],
            'sigle' => $d['sigle'] ?? null,
            'type' => $d['type'],
            'parent_id' => $parent?->id,
            'niveau' => $parent ? ((int) $parent->niveau + 1) : 1,
            'description' => $d['description'] ?? null,
            'actif' => $d['actif'] ?? true,
            'ordre' => $d['ordre'] ?? 0,
            'responsable_user_id' => $d['responsable_user_id'] ?? null,
        ]);
    }
}
