<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Support\DepenseVerification;
use Illuminate\Http\JsonResponse;

class VerificationApiController extends Controller
{
    public function show(string $type, int $id): JsonResponse
    {
        $objet = match ($type) {
            'besoin' => ExpressionBesoin::findOrFail($id),
            'engagement' => Engagement::findOrFail($id),
            'liquidation' => Liquidation::findOrFail($id),
            'ordonnancement' => Ordonnancement::findOrFail($id),
            'paiement' => Paiement::findOrFail($id),
            default => abort(404),
        };
        $this->authorize('view', $objet);

        return response()->json(DepenseVerification::api($type, $objet));
    }
}
