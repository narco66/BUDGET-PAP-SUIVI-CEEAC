<?php

namespace App\Services\Depense;

use App\Models\Exercice;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use Illuminate\Support\Collection;

/** Journaux consolidés L/O/P exportables (phase D3). */
class JournalConsolideService
{
    /** @return Collection<int, array<int, mixed>> */
    public function journalLiquidations(Exercice $exercice): Collection
    {
        return Liquidation::with('engagement.expressionBesoin', 'engagement.tache')
            ->whereHas('engagement', fn ($q) => $q->where('exercice_id', $exercice->id))
            ->orderBy('numero')
            ->get()
            ->map(fn (Liquidation $l) => [
                $l->numero,
                $l->engagement?->numero,
                $l->engagement?->expressionBesoin?->numero,
                $l->engagement?->tache?->code,
                $l->engagement?->objet,
                (float) $l->montant,
                $l->statut,
                $l->date_liquidation?->format('Y-m-d'),
                $l->valide_le?->format('Y-m-d H:i'),
            ]);
    }

    /** @return Collection<int, array<int, mixed>> */
    public function journalOrdonnancements(Exercice $exercice): Collection
    {
        return Ordonnancement::with('liquidation.engagement.expressionBesoin', 'liquidation.engagement.tache')
            ->whereHas('liquidation.engagement', fn ($q) => $q->where('exercice_id', $exercice->id))
            ->orderBy('numero')
            ->get()
            ->map(fn (Ordonnancement $o) => [
                $o->numero,
                $o->liquidation?->numero,
                $o->liquidation?->engagement?->numero,
                $o->liquidation?->engagement?->tache?->code,
                (float) $o->montant,
                $o->statut,
                $o->date_ordonnancement?->format('Y-m-d'),
                $o->valide_le?->format('Y-m-d H:i'),
            ]);
    }

    /** @return Collection<int, array<int, mixed>> */
    public function journalConsolide(Exercice $exercice): Collection
    {
        $lignes = collect();

        foreach ($this->journalLiquidations($exercice) as $row) {
            $lignes->push(array_merge(['Liquidation'], $row));
        }

        foreach ($this->journalOrdonnancements($exercice) as $row) {
            $lignes->push(array_merge(['Ordonnancement'], $row));
        }

        $paiements = Paiement::with('ordonnancement.liquidation.engagement.tache')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $exercice->id))
            ->orderBy('numero')
            ->get()
            ->map(fn (Paiement $p) => [
                'Paiement',
                $p->numero,
                $p->ordonnancement?->liquidation?->engagement?->numero,
                $p->ordonnancement?->numero,
                $p->ordonnancement?->liquidation?->engagement?->tache?->code,
                (float) $p->montant,
                $p->statut,
                $p->date_paiement?->format('Y-m-d'),
                $p->valide_le?->format('Y-m-d H:i'),
            ]);

        return $lignes->concat($paiements)->values();
    }
}
