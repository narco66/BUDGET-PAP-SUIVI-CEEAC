<?php

namespace App\Services\Depense;

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\LiquidationPiece;
use App\Models\OrdrePaiement;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\ServiceFait;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Services\Workflow\TacheQueryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Vue dossier transverse Besoin → Paiement (phase D2) : chaque étape porte ses visas et l'action humaine
 * en attente (issue de « Mes Tâches »), et le dossier indique où il en est et qui doit agir.
 */
class DepenseDossierService
{
    /** @var array<string, string> */
    private const STATUTS = [
        'brouillon' => 'Brouillon', 'soumis' => 'En validation', 'a_corriger' => 'À corriger',
        'valide' => 'Validé', 'vise' => 'Visé', 'rejete' => 'Rejeté', 'converti' => 'Converti en engagement',
        'en_attente_arbitrage' => 'En attente d\'arbitrage (crédits)', 'annule' => 'Annulé',
        'emis' => 'Émis', 'execute' => 'Exécuté',
    ];

    /** @var array<string, string> */
    private array $libellesRoles = [];

    /** @return array<string,mixed> */
    public function depuisEngagement(Engagement $engagement): array
    {
        $engagement->load([
            'exercice', 'activite', 'tache', 'expressionBesoin.tache', 'expressionBesoin.workflowActif.etapes.acteur',
            'imputations.ligneBudgetaire', 'workflowActif.etapes.acteur', 'pieces.auteur',
            'liquidations.ordonnancements.ordresPaiement', 'liquidations.ordonnancements.paiements',
            'liquidations.servicesFaits.responsable', 'liquidations.piecesLiquidation',
            'liquidations.workflowActif.etapes.acteur', 'liquidations.pieces.auteur',
        ]);

        return $this->composer($engagement->expressionBesoin, $engagement);
    }

    /** @return array<string,mixed> */
    public function depuisBesoin(ExpressionBesoin $besoin): array
    {
        // loadMissing : la fiche EB a déjà chargé une partie de ces relations (structure, demandeur, circuit, pièces).
        $besoin->loadMissing([
            'exercice', 'activite', 'tache', 'structure', 'demandeur', 'workflowActif.etapes.acteur', 'pieces.auteur',
            'engagements.liquidations.ordonnancements.ordresPaiement',
            'engagements.liquidations.ordonnancements.paiements',
            'engagements.liquidations.servicesFaits',
            'engagements.liquidations.piecesLiquidation',
        ]);

        $engagement = $besoin->engagements->sortByDesc('id')->first();

        return $this->composer($besoin, $engagement);
    }

    /** @return array<string,mixed> */
    private function composer(?ExpressionBesoin $besoin, ?Engagement $engagement): array
    {
        $timeline = collect();

        if ($besoin) {
            $timeline->push($this->enrichir($this->etapeBesoin($besoin), $besoin));
        }

        if ($engagement) {
            $timeline->push($this->enrichir($this->etapeEngagement($engagement), $engagement));

            foreach ($engagement->liquidations as $liq) {
                $timeline->push($this->enrichir($this->etapeLiquidation($liq), $liq));

                foreach ($liq->ordonnancements as $ord) {
                    $timeline->push($this->enrichir($this->etapeOrdonnancement($ord), $ord));

                    foreach ($ord->ordresPaiement as $op) {
                        $timeline->push($this->enrichir($this->etapeOrdrePaiement($op), null));
                    }

                    foreach ($ord->paiements as $pay) {
                        $timeline->push($this->enrichir($this->etapePaiement($pay), $pay));
                    }
                }
            }
        }

        return [
            'besoin' => $besoin,
            'engagement' => $engagement,
            'tache' => $engagement?->tache ?? $besoin?->tache,
            'priorite' => $besoin?->priorite,
            'timeline' => $timeline->values(),
            'situation' => $this->situation($timeline),
            'pieces' => $this->piecesUnifiees($besoin, $engagement),
        ];
    }

    /**
     * Où en est le dossier : les actions humaines attendues (« Mes Tâches »), sinon la dernière étape atteinte.
     *
     * @param  Collection<int, array<string,mixed>>  $timeline
     * @return array{en_attente: list<array<string,mixed>>, derniere: ?array<string,mixed>}
     */
    private function situation(Collection $timeline): array
    {
        return [
            'en_attente' => $timeline->filter(fn ($e) => $e['en_attente'] !== null)->values()->all(),
            'derniere' => $timeline->last(),
        ];
    }

    /**
     * Ajoute à une étape son statut lisible, ses visas (qui a validé et quand) et l'action en attente.
     *
     * @param  array<string,mixed>  $etape
     * @return array<string,mixed>
     */
    private function enrichir(array $etape, ?Model $objet): array
    {
        $etape['statut_libelle'] = self::STATUTS[$etape['statut']] ?? ucfirst(str_replace('_', ' ', (string) $etape['statut']));
        $etape['visas'] = $etape['workflow']
            // Étapes déjà chargées avec le circuit (workflowActif.etapes.acteur) : pas de nouvelle lecture.
            ? ($etape['workflow']->relationLoaded('etapes')
                ? $etape['workflow']->etapes->loadMissing('acteur')->sortBy('ordre')->values()
                : $etape['workflow']->etapes()->with('acteur')->orderBy('ordre')->get())->map(fn ($e) => [
                'ordre' => $e->ordre,
                'libelle' => $e->libelle ?: $this->libelleRole($e->role),
                'role' => $this->libelleRole($e->role),
                'statut' => $e->statut,
                'acteur' => $e->acteur?->name,
                'date' => $e->decision_le,
                'observation' => $e->observation,
            ])->all()
            : [];
        $etape['en_attente'] = $objet ? $this->actionEnAttente($objet) : null;

        return $etape;
    }

    /** @return array<string,mixed>|null action humaine ouverte sur le dossier, avec ceux qui peuvent la réaliser */
    private function actionEnAttente(Model $objet): ?array
    {
        $tache = WorkflowTache::query()->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())
            ->ouvertes()->latest('id')->first();
        if (! $tache) {
            return null;
        }
        $requetes = app(TacheQueryService::class);
        $titulaires = User::query()->where('actif', true)->role($tache->role)->orderBy('name')->get()
            ->filter(fn (User $u) => $requetes->peutTraiter($tache, $u))->pluck('name')->values()->all();

        return [
            'tache' => $tache,
            'libelle' => $tache->libelle_etape ?: $this->libelleRole($tache->role),
            'role' => $this->libelleRole($tache->role),
            'titulaires' => $titulaires,
            'pris_en_charge_par' => $tache->assigne?->name,
            'recue_le' => $tache->created_at,
            'echeance_le' => $tache->echeance_le,
            'en_retard' => $tache->enRetard(),
            'hors_circuit' => $tache->estPreparation(),
        ];
    }

    private function libelleRole(?string $role): string
    {
        if (! $role) {
            return '—';
        }

        return $this->libellesRoles[$role] ??= (string) (Role::query()->where('name', $role)->value('libelle') ?: str_replace('_', ' ', $role));
    }

    /** @return array<string,mixed> */
    private function etapeBesoin(ExpressionBesoin $besoin): array
    {
        return [
            'type' => 'besoin',
            'label' => 'Expression de besoin',
            'numero' => $besoin->numero,
            'statut' => $besoin->statut,
            'montant' => (float) $besoin->montant_estime,
            'date' => $besoin->date_besoin,
            'route' => route('besoins.show', $besoin),
            'workflow' => $besoin->workflowActif,
        ];
    }

    /** @return array<string,mixed> */
    private function etapeEngagement(Engagement $engagement): array
    {
        return [
            'type' => 'engagement',
            'label' => 'Engagement',
            'numero' => $engagement->numero,
            'statut' => $engagement->statut,
            'montant' => (float) $engagement->montant_total,
            'date' => $engagement->date_engagement,
            'route' => route('engagements.show', $engagement),
            'workflow' => $engagement->workflowActif,
        ];
    }

    /** @return array<string,mixed> */
    private function etapeLiquidation(Liquidation $liq): array
    {
        return [
            'type' => 'liquidation',
            'label' => 'Liquidation',
            'numero' => $liq->numero,
            'statut' => $liq->statut,
            'montant' => (float) $liq->montant,
            'date' => $liq->date_liquidation,
            'route' => route('depense.show', ['liquidation', $liq->id]),
            'workflow' => $liq->workflowActif,
            'services_faits' => $liq->servicesFaits,
            'pieces_liquidation' => $liq->piecesLiquidation,
        ];
    }

    /** @return array<string,mixed> */
    private function etapeOrdonnancement(Ordonnancement $ord): array
    {
        return [
            'type' => 'ordonnancement',
            'label' => 'Ordonnancement',
            'numero' => $ord->numero,
            'statut' => $ord->statut,
            'montant' => (float) $ord->montant,
            'date' => $ord->date_ordonnancement,
            'route' => route('depense.show', ['ordonnancement', $ord->id]),
            'workflow' => $ord->workflowActif,
        ];
    }

    /** @return array<string,mixed> */
    private function etapeOrdrePaiement(OrdrePaiement $op): array
    {
        return [
            'type' => 'ordre_paiement',
            'label' => 'Ordre de paiement',
            'numero' => $op->numero,
            'statut' => $op->statut,
            'montant' => (float) $op->montant,
            'date' => $op->date_ordre,
            'route' => null,
            'workflow' => null,
        ];
    }

    /** @return array<string,mixed> */
    private function etapePaiement(Paiement $pay): array
    {
        return [
            'type' => 'paiement',
            'label' => 'Paiement',
            'numero' => $pay->numero,
            'statut' => $pay->statut,
            'montant' => (float) $pay->montant,
            'date' => $pay->date_paiement,
            'route' => route('depense.show', ['paiement', $pay->id]),
            'workflow' => $pay->workflowActif,
        ];
    }

    /** @return Collection<int, mixed> */
    private function piecesUnifiees(?ExpressionBesoin $besoin, ?Engagement $engagement): Collection
    {
        $pieces = collect();
        if ($besoin) {
            foreach ($besoin->pieces as $p) {
                $pieces->push(['etape' => 'Besoin', 'numero' => $besoin->numero, 'piece' => $p]);
            }
        }

        if (! $engagement) {
            return $pieces;
        }

        foreach ($engagement->pieces as $p) {
            $pieces->push(['etape' => 'Engagement', 'numero' => $engagement->numero, 'piece' => $p]);
        }

        foreach ($engagement->liquidations as $liq) {
            foreach ($liq->pieces as $p) {
                $pieces->push(['etape' => 'Liquidation', 'numero' => $liq->numero, 'piece' => $p]);
            }
            foreach ($liq->piecesLiquidation as $lp) {
                $pieces->push([
                    'etape' => 'Liquidation',
                    'numero' => $liq->numero,
                    'typologie' => LiquidationPiece::TYPES[$lp->type] ?? $lp->type,
                    'reference' => $lp->reference,
                    'date' => $lp->date_piece,
                ]);
            }
        }

        return $pieces;
    }
}
