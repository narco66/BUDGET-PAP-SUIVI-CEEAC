<?php

namespace App\Observers;

use App\Models\Activite;
use App\Models\LigneBudgetaire;
use App\Services\Budget\RbmBudgetSyncService;

/** Synchronisation automatique bidirectionnelle Activité ↔ Ligne budgétaire à chaque écriture. */
class RbmBudgetSyncObserver
{
    private static int $profondeur = 0;

    public function saved(LigneBudgetaire $ligne): void
    {
        if (self::$profondeur > 0) {
            return;
        }

        if (! $ligne->wasRecentlyCreated && ! $ligne->wasChanged('activite_id')) {
            return;
        }

        self::$profondeur++;
        try {
            app(RbmBudgetSyncService::class)->synchroniserLigne($ligne->fresh());
        } finally {
            self::$profondeur--;
        }
    }

    public function savedActivite(Activite $activite): void
    {
        if (self::$profondeur > 0) {
            return;
        }

        if (! $activite->wasChanged('ligne_budgetaire_id')) {
            return;
        }

        self::$profondeur++;
        try {
            app(RbmBudgetSyncService::class)->synchroniserActivite($activite->fresh());
        } finally {
            self::$profondeur--;
        }
    }
}
