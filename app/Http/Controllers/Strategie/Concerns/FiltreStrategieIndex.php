<?php

namespace App\Http\Controllers\Strategie\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltreStrategieIndex
{
    protected function appliquerFiltresIndex(Builder $query, Request $request, array $searchColumns = ['reference', 'intitule']): Builder
    {
        if ($statut = $request->string('statut')->toString()) {
            $query->where('statut', $statut);
        }

        if ($q = trim($request->string('q')->toString())) {
            $query->where(function (Builder $sub) use ($searchColumns, $q) {
                foreach ($searchColumns as $col) {
                    $sub->orWhere($col, 'like', "%{$q}%");
                }
            });
        }

        return $query;
    }
}
