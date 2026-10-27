<?php

namespace App\Services\Import;

use App\Models\ImportMapping;
use App\Models\User;
use App\Services\Import\Domaines\DomaineImport;
use Illuminate\Support\Str;

/**
 * Correspondance colonne source → champ métier : détection automatique (en-têtes comparés sans accents ni
 * casse, avec les synonymes du domaine), ajustement manuel et modèles réutilisables par domaine.
 */
class ImportMappingService
{
    public static function normaliser(string $entete): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(Str::ascii($entete))), '_');
    }

    /**
     * @param  list<string>  $entetes
     * @return array<string, ?string> champ => en-tête source (null si non trouvé)
     */
    public function detecter(DomaineImport $domaine, array $entetes): array
    {
        $parNorme = [];
        foreach ($entetes as $e) {
            $parNorme[self::normaliser($e)] ??= $e;
        }
        $mapping = [];
        foreach ($domaine->champs() as $champ) {
            $mapping[$champ->champ] = null;
            foreach (array_merge([$champ->champ, $champ->libelle], $champ->synonymes) as $candidat) {
                $cle = self::normaliser($candidat);
                if (isset($parNorme[$cle])) {
                    $mapping[$champ->champ] = $parNorme[$cle];
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Applique un modèle enregistré, limité aux en-têtes présents dans le fichier.
     *
     * @param  list<string>  $entetes
     * @return array<string, ?string>
     */
    public function appliquerModele(ImportMapping $modele, array $entetes): array
    {
        return array_map(fn ($entete) => in_array($entete, $entetes, true) ? $entete : null, $modele->colonnes);
    }

    /**
     * Ne conserve que les champs du domaine et les en-têtes réellement présents.
     *
     * @param  array<string, mixed>  $saisie
     * @param  list<string>  $entetes
     * @return array<string, ?string>
     */
    public function nettoyer(DomaineImport $domaine, array $saisie, array $entetes): array
    {
        $mapping = [];
        foreach ($domaine->champs() as $champ) {
            $valeur = $saisie[$champ->champ] ?? null;
            $mapping[$champ->champ] = is_string($valeur) && in_array($valeur, $entetes, true) ? $valeur : null;
        }

        return $mapping;
    }

    /** @return list<string> libellés des champs obligatoires sans colonne source */
    public function champsRequisManquants(DomaineImport $domaine, array $mapping): array
    {
        return collect($domaine->champs())
            ->filter(fn ($c) => $c->requis && empty($mapping[$c->champ]))
            ->map(fn ($c) => $c->libelle)->values()->all();
    }

    /**
     * @param  list<string>  $entetes
     * @return list<string> colonnes du fichier qui ne sont associées à aucun champ (ignorées)
     */
    public function colonnesIgnorees(array $mapping, array $entetes): array
    {
        return array_values(array_diff($entetes, array_filter($mapping)));
    }

    public function enregistrerModele(DomaineImport $domaine, string $nom, array $mapping, User $user): ImportMapping
    {
        return ImportMapping::query()->updateOrCreate(
            ['domaine' => $domaine->code(), 'nom' => trim($nom)],
            ['colonnes' => array_filter($mapping), 'cree_par' => $user->id],
        );
    }
}
