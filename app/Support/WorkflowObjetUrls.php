<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\Collecte;
use App\Models\Encaissement;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Pap;
use App\Models\Rapport;
use App\Models\Strategie\PlanStrategique;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Model;

/** Liens vers les fiches métier depuis une instance workflow (corbeille P1). */
final class WorkflowObjetUrls
{
    public static function show(?Model $objet): ?string
    {
        if (! $objet) {
            return null;
        }

        return match ($objet::class) {
            Pap::class => route('paps.show', $objet),
            Budget::class => route('budget.enveloppe.show', $objet),
            ExpressionBesoin::class => route('besoins.show', $objet),
            Engagement::class => route('engagements.show', $objet),
            Encaissement::class => route('encaissements.show', $objet),
            Collecte::class => $objet->campagne_id ? route('suivi.show', $objet->campagne_id) : null,
            Rapport::class => route('reporting.historique'),
            PlanStrategique::class => route('strategie.plans.show', $objet),
            Liquidation::class => route('depense.show', ['liquidation', $objet->getKey()]),
            Ordonnancement::class => route('depense.show', ['ordonnancement', $objet->getKey()]),
            Paiement::class => route('depense.show', ['paiement', $objet->getKey()]),
            default => null,
        };
    }

    public static function libelle(?Model $objet): string
    {
        if (! $objet) {
            return '—';
        }

        return match (true) {
            isset($objet->reference) => (string) $objet->reference,
            isset($objet->numero) => (string) $objet->numero,
            default => class_basename($objet).' #'.$objet->getKey(),
        };
    }

    public static function typeLibelle(WorkflowInstance $instance): string
    {
        return $instance->workflow?->libelle ?? ($instance->workflow?->code ?? 'Workflow');
    }
}
