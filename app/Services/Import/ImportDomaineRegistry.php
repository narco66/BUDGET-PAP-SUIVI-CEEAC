<?php

namespace App\Services\Import;

use App\Services\Import\Domaines\DomaineImport;
use App\Services\Import\Domaines\LignesBudgetairesDomaine;
use App\Services\Import\Domaines\MesuresIndicateursDomaine;
use App\Services\Import\Domaines\NomenclatureDomaine;
use App\Services\Import\Domaines\StructuresDomaine;
use InvalidArgumentException;

/** Catalogue des domaines d'import : disponibles (stratégie implémentée) et planifiés (affichés, non activés). */
class ImportDomaineRegistry
{
    /** @var list<class-string<DomaineImport>> */
    private const DISPONIBLES = [
        StructuresDomaine::class,
        NomenclatureDomaine::class,
        LignesBudgetairesDomaine::class,
        MesuresIndicateursDomaine::class,
    ];

    /** Domaines prévus par la description fonctionnelle, non encore implémentés (règles à arbitrer). */
    public const PLANIFIES = [
        'pap' => ['P1', 'Plan d\'Action Prioritaire (Pilier → Tâche)', 'Import existant de remplacement complet à intégrer au cycle contrôlé.'],
        'utilisateurs' => ['P2', 'Utilisateurs et affectations', 'Création de comptes et rôles soumise à la procédure d\'habilitation.'],
        'recettes' => ['P2', 'Recettes (prévisions et encaissements)', 'Règles de rapprochement à arbitrer.'],
        'depense_historique' => ['P3', 'Reprise historique EB / ENG / LIQ / ORD / PAY', 'Exigences d\'audit renforcées : aucune approbation fabriquée.'],
    ];

    /** @return array<string, DomaineImport> */
    public function disponibles(): array
    {
        $domaines = [];
        foreach (self::DISPONIBLES as $classe) {
            $d = app($classe);
            $domaines[$d->code()] = $d;
        }

        return $domaines;
    }

    public function get(string $code): DomaineImport
    {
        return $this->disponibles()[$code] ?? throw new InvalidArgumentException("Domaine d'import inconnu : {$code}.");
    }

    public function existe(string $code): bool
    {
        return isset($this->disponibles()[$code]);
    }
}
