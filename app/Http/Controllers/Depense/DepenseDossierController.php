<?php



namespace App\Http\Controllers\Depense;



use App\Http\Controllers\Controller;

use App\Models\Engagement;

use App\Models\ExpressionBesoin;

use App\Services\Depense\DepenseDossierService;

use Illuminate\View\View;



/** Vue dossier unifié Besoin → Paiement (phase D2). */

class DepenseDossierController extends Controller

{

    public function __construct(private readonly DepenseDossierService $dossiers)

    {

    }



    public function engagement(Engagement $engagement): View

    {

        $this->authorize('view', $engagement);



        return view('depense.dossier', $this->dossiers->depuisEngagement($engagement));

    }



    public function besoin(ExpressionBesoin $besoin): View

    {

        $this->authorize('view', $besoin);



        return view('depense.dossier', $this->dossiers->depuisBesoin($besoin));

    }

}


