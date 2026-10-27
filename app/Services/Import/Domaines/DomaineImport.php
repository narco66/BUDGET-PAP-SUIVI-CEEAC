<?php

namespace App\Services\Import\Domaines;

use App\Models\ImportLigne;
use App\Models\User;
use App\Services\Import\Support\AnalyseLigne;
use App\Services\Import\Support\ChampImport;
use App\Services\Import\Support\ContexteImport;
use Illuminate\Database\Eloquent\Model;

/**
 * Stratégie d'import d'un domaine : champs attendus, règles métier, rapprochement avec la base et écriture.
 * L'analyse ne doit RIEN écrire ; l'exécution n'écrit que ce que l'analyse a proposé et autorisé.
 */
interface DomaineImport
{
    public function code(): string;

    public function libelle(): string;

    public function description(): string;

    /** @return list<ChampImport> */
    public function champs(): array;

    public function exigeExercice(): bool;

    /** @return list<string> modes autorisés (insertion, mise_a_jour, simulation) */
    public function modes(): array;

    /** Circuit de workflow d'approbation (lot sensible), ou null si l'import ne requiert pas d'approbation distincte. */
    public function circuit(): ?string;

    /** L'utilisateur peut-il importer dans ce domaine (droit métier sur les données cibles) ? */
    public function autorise(User $user): bool;

    /** @return list<array<string, mixed>> lignes d'exemple du modèle téléchargeable */
    public function exemple(): array;

    /** Chargement des référentiels utiles avant l'analyse (lecture seule). */
    public function preparer(ContexteImport $ctx): void;

    /**
     * Pré-passe sur toutes les lignes normalisées, avant l'analyse ligne à ligne : déclare les entités prévues
     * dans le lot (un enfant peut précéder son parent dans le fichier).
     *
     * @param  list<array<string, mixed>>  $lignes
     */
    public function prevoir(array $lignes, ContexteImport $ctx): void;

    /** Analyse d'une ligne normalisée : contrôles métier, clé, rapprochement, opération proposée. */
    public function analyser(AnalyseLigne $ligne, int $numero, ContexteImport $ctx): void;

    /** Rang d'exécution (les parents avant leurs enfants). */
    public function rangExecution(ImportLigne $ligne): int;

    /** Écrit la ligne (insertion ou mise à jour autorisée) et retourne l'entité concernée. */
    public function executer(ImportLigne $ligne, ContexteImport $ctx): ?Model;
}
