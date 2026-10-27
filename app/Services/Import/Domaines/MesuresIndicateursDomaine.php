<?php

namespace App\Services\Import\Domaines;

use App\Models\ImportLigne;
use App\Models\Indicateur;
use App\Models\User;
use App\Services\Import\Support\AnalyseLigne;
use App\Services\Import\Support\ChampImport;
use App\Services\Import\Support\ContexteImport;
use App\Services\Rbm\MesureService;
use Illuminate\Database\Eloquent\Model;

/**
 * Suivi-évaluation — mesures d'indicateurs (P2). Écriture par le service métier (taux d'atteinte, feu,
 * historique) : la mesure importée entre au statut « saisie » et suit son circuit normal de vérification /
 * validation ; une mesure validée n'est jamais modifiée.
 */
class MesuresIndicateursDomaine implements DomaineImport
{
    public function __construct(private readonly MesureService $mesures) {}

    public function code(): string
    {
        return 'mesures_indicateurs';
    }

    public function libelle(): string
    {
        return 'Suivi-évaluation — mesures d\'indicateurs';
    }

    public function description(): string
    {
        return 'Réalisations des indicateurs par période (saisies soumises ensuite à vérification et validation).';
    }

    public function champs(): array
    {
        return [
            new ChampImport('indicateur_code', 'Code de l\'indicateur', true, 'code', ['indicateur', 'code_indicateur']),
            new ChampImport('periode', 'Période (ex. 2026-T1)', true, 'code', ['trimestre', 'mois']),
            new ChampImport('valeur_realisee', 'Valeur réalisée', true, 'decimal', ['realisation', 'valeur']),
            new ChampImport('commentaire', 'Commentaire / source de vérification', false, 'texte', ['observation', 'source']),
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
        return null;
    }

    public function autorise(User $user): bool
    {
        return $user->can('indicateur.creer') || $user->can('suivi.creer');
    }

    public function exemple(): array
    {
        return [['indicateur_code' => 'IND-2011100', 'periode' => '2026-T2', 'valeur_realisee' => '65', 'commentaire' => 'Rapport trimestriel du service']];
    }

    public function preparer(ContexteImport $ctx): void
    {
        $ctx->cache['indicateurs'] = Indicateur::query()->with('mesures')->get()->keyBy(fn ($i) => mb_strtoupper($i->code));
    }

    public function prevoir(array $lignes, ContexteImport $ctx): void
    {
    }

    public function analyser(AnalyseLigne $ligne, int $numero, ContexteImport $ctx): void
    {
        $d = $ligne->donnees;
        $ligne->cle = ($d['indicateur_code'] ?? '').'|'.($d['periode'] ?? '');
        /** @var Indicateur|null $indicateur */
        $indicateur = $ctx->cache['indicateurs'][$d['indicateur_code'] ?? ''] ?? null;
        if (! $indicateur) {
            $ligne->bloquante('reference_inexistante', "Indicateur « {$d['indicateur_code']} » introuvable.", 'indicateur_code', $d['indicateur_code'] ?? null);

            return;
        }
        $ligne->donnees['indicateur_id'] = $indicateur->id;

        $mesure = $indicateur->mesures->firstWhere('periode', $d['periode']);
        if (! $mesure) {
            $ligne->operation = 'inserer';

            return;
        }
        $ligne->cible = $mesure;
        if ($mesure->statut === 'validee') {
            $ligne->majeure('mesure_validee', "Mesure {$d['periode']} déjà validée : modification interdite.", 'periode', $d['periode'], 'Une correction passe par le circuit de correction des mesures.');
            $ligne->operation = 'conflit';

            return;
        }
        if (round((float) $mesure->valeur_realisee, 4) !== round((float) $d['valeur_realisee'], 4)) {
            $ligne->difference('valeur_realisee', $mesure->valeur_realisee, $d['valeur_realisee']);
        }
        if (($d['commentaire'] ?? null) !== null && $d['commentaire'] !== $mesure->commentaire) {
            $ligne->difference('commentaire', $mesure->commentaire, $d['commentaire']);
        }
        $ligne->operation = match (true) {
            $ligne->differences === [] => 'identique',
            $ctx->miseAJourAutorisee() => 'mettre_a_jour',
            default => 'ignorer',
        };
        if ($ligne->operation === 'ignorer') {
            $ligne->avertissement('existe_deja', "Mesure {$d['periode']} déjà saisie avec une autre valeur : non modifiée en mode « insertion ».", 'valeur_realisee', $d['valeur_realisee']);
        }
    }

    public function rangExecution(ImportLigne $ligne): int
    {
        return 1;
    }

    public function executer(ImportLigne $ligne, ContexteImport $ctx): ?Model
    {
        $d = $ligne->donnees;
        $indicateur = Indicateur::query()->findOrFail($d['indicateur_id']);

        return $this->mesures->enregistrer($indicateur, [
            'periode' => $d['periode'],
            'valeur_realisee' => $d['valeur_realisee'],
            'commentaire' => $d['commentaire'] ?? null,
        ], $ctx->user?->id);
    }
}
