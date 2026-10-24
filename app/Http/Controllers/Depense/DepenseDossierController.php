<?php

namespace App\Http\Controllers\Depense;

use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Services\Depense\ChaineClotureService;
use App\Services\Depense\DepenseDossierService;
use App\Support\DepenseVerification;
use Illuminate\View\View;

/** Vue dossier unifié Besoin → Paiement (phase D2). */
class DepenseDossierController extends Controller
{
    public function __construct(
        private readonly DepenseDossierService $dossiers,
        private readonly ChaineClotureService $cloture,
    ) {}

    public function engagement(Engagement $engagement): View
    {
        $this->authorize('view', $engagement);
        $payload = $this->dossiers->depuisEngagement($engagement);
        $payload['cloture'] = $this->cloture->etat($engagement);

        return view('depense.dossier', $payload);
    }

    public function besoin(ExpressionBesoin $besoin): View
    {
        $this->authorize('view', $besoin);
        $payload = $this->dossiers->depuisBesoin($besoin);
        $payload['cloture'] = $payload['engagement']
            ? $this->cloture->etat($payload['engagement'])
            : null;

        return view('depense.dossier', $payload);
    }

    public function verifier(string $type, int $id): View
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

        return view('depense.verifier', [
            'type' => $type,
            'objet' => $objet,
            'empreinte' => DepenseVerification::empreinte($type, $objet),
        ]);
    }
}
