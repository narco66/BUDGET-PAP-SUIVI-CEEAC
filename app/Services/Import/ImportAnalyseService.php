<?php

namespace App\Services\Import;

use App\Models\ImportAnomalie;
use App\Models\ImportLigne;
use App\Models\ImportLot;
use App\Services\Import\Domaines\DomaineImport;
use App\Services\Import\Support\AnalyseLigne;
use App\Services\Import\Support\ContexteImport;
use App\Services\Import\Support\Normaliseur;
use Illuminate\Support\Facades\DB;

/**
 * Validation, rapprochement et simulation d'un lot : chaque ligne est normalisée, contrôlée (syntaxe,
 * référentiels, règles financières du domaine), rapprochée de la base et dotée d'une opération proposée.
 * Seules les tables de préparation du lot (import_lignes, import_anomalies) sont écrites — jamais les
 * tables métier.
 */
class ImportAnalyseService
{
    public function __construct(
        private readonly ImportFichierService $fichiers,
        private readonly ImportDomaineRegistry $domaines,
    ) {}

    /**
     * Calcule l'analyse complète sans rien persister.
     *
     * @return list<array{numero: int, brutes: array<string, mixed>, analyse: AnalyseLigne}>
     */
    public function calculer(ImportLot $lot, ?DomaineImport $domaine = null): array
    {
        $domaine ??= $this->domaines->get($lot->domaine);
        $lu = $this->fichiers->lire($lot);
        $ctx = new ContexteImport($lot, $lot->exercice, $lot->mode, $lot->createur);
        $domaine->preparer($ctx);
        $mapping = $lot->mapping ?? [];
        $vues = [];
        $resultats = [];

        // 1re passe : normalisation et contrôles de format de toutes les lignes.
        foreach ($lu['lignes'] as ['numero' => $numero, 'valeurs' => $brutes]) {
            $analyse = new AnalyseLigne([]);
            $donnees = [];
            foreach ($domaine->champs() as $champ) {
                $entete = $mapping[$champ->champ] ?? null;
                $valeur = $entete !== null ? ($brutes[$entete] ?? null) : null;
                if (Normaliseur::estFormule($valeur)) {
                    $analyse->avertissement('injection_formule', 'Valeur interprétable comme une formule de tableur : conservée comme texte et neutralisée dans les exports.', $champ->champ, $valeur);
                }
                [$norme, $erreur] = Normaliseur::normaliser($valeur, $champ->type);
                if ($erreur) {
                    $analyse->bloquante('format_'.$champ->type, $erreur, $champ->champ, $valeur);
                } elseif ($champ->requis && $norme === null) {
                    $analyse->bloquante('valeur_requise', "« {$champ->libelle} » est obligatoire.", $champ->champ);
                }
                $donnees[$champ->champ] = $norme;
            }
            $analyse->donnees = $donnees;
            $resultats[] = ['numero' => $numero, 'brutes' => $brutes, 'analyse' => $analyse];
        }

        // Pré-passe métier : entités prévues dans le lot (un enfant peut précéder son parent dans le fichier).
        $domaine->prevoir(array_values(array_map(fn ($r) => $r['analyse']->donnees, array_filter($resultats, fn ($r) => ! $r['analyse']->aDesBloquantes()))), $ctx);

        // 2e passe : règles métier, rapprochement avec la base, doublons dans le fichier.
        foreach ($resultats as ['numero' => $numero, 'analyse' => $analyse]) {
            if (! $analyse->aDesBloquantes()) {
                $domaine->analyser($analyse, $numero, $ctx);
            }
            if ($analyse->cle !== null && $analyse->cle !== '') {
                if (isset($vues[$analyse->cle])) {
                    $analyse->bloquante('doublon_fichier', "Doublon : même clé que la ligne {$vues[$analyse->cle]} du fichier.", null, $analyse->cle, 'Supprimer ou fusionner la ligne en double.');
                } else {
                    $vues[$analyse->cle] = $numero;
                }
            }
            if ($analyse->aDesBloquantes()) {
                $analyse->operation = 'erreur';
            }
        }

        return $resultats;
    }

    /** Analyse et persiste le plan d'opérations et les anomalies du lot (réanalyse : remplace le précédent). */
    public function analyser(ImportLot $lot): ImportLot
    {
        $domaine = $this->domaines->get($lot->domaine);
        $resultats = $this->calculer($lot, $domaine);

        DB::transaction(function () use ($lot, $resultats) {
            $lot->anomalies()->delete();
            $lot->lignes()->delete();

            $dejaImporte = ImportLot::query()->where('domaine', $lot->domaine)->where('fichier_empreinte', $lot->fichier_empreinte)
                ->where('id', '!=', $lot->id)->where('statut', 'termine')->value('reference');
            if ($dejaImporte) {
                ImportAnomalie::create(['import_lot_id' => $lot->id, 'regle' => 'fichier_deja_importe', 'gravite' => 'avertissement',
                    'message' => "Ce fichier (même empreinte) a déjà été importé par le lot {$dejaImporte} : les lignes déjà présentes seront « identiques » et non réécrites."]);
            }

            foreach ($resultats as ['numero' => $numero, 'brutes' => $brutes, 'analyse' => $a]) {
                $ligne = ImportLigne::create([
                    'import_lot_id' => $lot->id,
                    'numero' => $numero,
                    'donnees_brutes' => $brutes,
                    'donnees' => $a->donnees,
                    'cle_metier' => $a->cle !== null ? mb_substr($a->cle, 0, 255) : null,
                    'operation' => $a->operation,
                    'cible_type' => $a->cible?->getMorphClass(),
                    'cible_id' => $a->cible?->getKey(),
                    'differences' => $a->differences ?: null,
                ]);
                foreach ($a->anomalies as $anomalie) {
                    ImportAnomalie::create([
                        'import_lot_id' => $lot->id,
                        'import_ligne_id' => $ligne->id,
                        'numero_ligne' => $numero,
                        'colonne' => $anomalie['colonne'],
                        'valeur' => $anomalie['valeur'] === null ? null : mb_substr(is_scalar($anomalie['valeur']) ? (string) $anomalie['valeur'] : json_encode($anomalie['valeur']), 0, 2000),
                        'regle' => $anomalie['regle'],
                        'gravite' => $anomalie['gravite'],
                        'message' => $anomalie['message'],
                        'suggestion' => $anomalie['suggestion'],
                    ]);
                }
            }

            $lot->update(['totaux' => $this->totaux($lot), 'analyse_le' => now()]);
        });

        return $lot->fresh();
    }

    /** @return array<string, int|float> volumes par opération et par gravité, montant total des écritures */
    public function totaux(ImportLot $lot): array
    {
        $parOperation = $lot->lignes()->selectRaw('operation, count(*) n')->groupBy('operation')->pluck('n', 'operation');
        $parGravite = $lot->anomalies()->selectRaw('gravite, count(*) n')->groupBy('gravite')->pluck('n', 'gravite');
        $montant = 0.0;
        foreach ($lot->lignes()->whereIn('operation', ImportLigne::ECRITURES)->get(['donnees']) as $l) {
            $montant += (float) ($l->donnees['montant_ouvert'] ?? 0);
        }

        return [
            'lignes' => (int) $parOperation->sum(),
            'inserer' => (int) ($parOperation['inserer'] ?? 0),
            'mettre_a_jour' => (int) ($parOperation['mettre_a_jour'] ?? 0),
            'identique' => (int) ($parOperation['identique'] ?? 0),
            'ignorer' => (int) ($parOperation['ignorer'] ?? 0),
            'conflit' => (int) ($parOperation['conflit'] ?? 0),
            'erreur' => (int) ($parOperation['erreur'] ?? 0),
            'bloquantes' => (int) ($parGravite['bloquante'] ?? 0),
            'majeures' => (int) ($parGravite['majeure'] ?? 0),
            'avertissements' => (int) ($parGravite['avertissement'] ?? 0),
            'a_ecrire' => (int) (($parOperation['inserer'] ?? 0) + ($parOperation['mettre_a_jour'] ?? 0)),
            'montant' => round($montant, 2),
        ];
    }
}
