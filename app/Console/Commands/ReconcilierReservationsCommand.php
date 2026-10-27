<?php

namespace App\Console\Commands;

use App\Models\Engagement;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Réconcilie les réservations de crédits actives avec les imputations des engagements à crédits réservés.
 * Les imputations (et le compteur « engagé » des lignes, qui en dépend) font foi : seule la table de suivi des
 * réservations est ajustée, jamais un montant budgétaire. Simulation par défaut ; chaque ajustement est journalisé
 * avec les valeurs avant / après (retour arrière possible).
 */
class ReconcilierReservationsCommand extends Command
{
    protected $signature = 'depense:reconcilier-reservations {--appliquer : enregistre les ajustements (sinon simulation)}';

    protected $description = 'Aligne les réservations de crédits actives sur les imputations des engagements';

    public function handle(): int
    {
        $ajustements = 0;
        Engagement::query()->where('credits_reserves', true)->with(['imputations', 'reservationsCredits'])->orderBy('id')
            ->each(function (Engagement $e) use (&$ajustements) {
                foreach ($e->imputations->groupBy('ligne_budgetaire_id') as $ligneId => $imps) {
                    $attendu = round((float) $imps->sum('montant'), 2);
                    $actives = $e->reservationsCredits->where('ligne_budgetaire_id', (int) $ligneId)->where('statut', 'active')->sortByDesc('id');
                    $reserve = round((float) $actives->sum('montant'), 2);
                    if (abs($reserve - $attendu) <= 0.01) {
                        continue;
                    }
                    $this->line("{$e->numero} · ligne {$ligneId} : réservé {$reserve} → imputé {$attendu}");
                    $ajustements++;
                    if (! $this->option('appliquer') || $reserve < $attendu) {
                        if ($reserve < $attendu) {
                            $this->warn('  réservation inférieure à l\'imputation : analyse manuelle requise, aucun ajustement automatique.');
                        }

                        continue;
                    }
                    DB::transaction(function () use ($e, $ligneId, $actives, $reserve, $attendu) {
                        $avant = $actives->map(fn ($r) => ['id' => $r->id, 'montant' => (float) $r->montant, 'statut' => $r->statut])->values()->all();
                        $exces = round($reserve - $attendu, 2);
                        foreach ($actives as $r) {
                            if ($exces <= 0.005) {
                                break;
                            }
                            $prise = min((float) $r->montant, $exces);
                            $nouveau = round((float) $r->montant - $prise, 2);
                            $r->update($nouveau <= 0.005 ? ['montant' => 0, 'statut' => 'liberee', 'liberee_le' => now()] : ['montant' => $nouveau]);
                            $exces = round($exces - $prise, 2);
                        }
                        AuditLogger::evenement('reservation_credits_reconciliee', $e, [
                            'module' => 'DEPENSE',
                            'reference' => $e->numero,
                            'motif' => 'Réservation non réduite lors d\'un dégagement partiel (défaut corrigé dans EngagementService::degager).',
                            'avant' => ['ligne_budgetaire_id' => (int) $ligneId, 'reservations' => $avant],
                            'apres' => ['ligne_budgetaire_id' => (int) $ligneId, 'reserve' => $attendu],
                        ]);
                    });
                }
            });
        $this->info(($this->option('appliquer') ? '' : '[simulation] ')."Écarts détectés : {$ajustements}.");

        return self::SUCCESS;
    }
}
