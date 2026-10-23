<?php



namespace App\Http\Controllers\Strategie;



use App\Http\Controllers\Controller;

use App\Services\Strategie\StrategieAlignementService;

use App\Services\Strategie\StrategieReferentielService;

use Illuminate\View\View;



class StrategieModuleController extends Controller

{

    public function __construct(

        private readonly StrategieReferentielService $service,

        private readonly StrategieAlignementService $alignement,

    ) {

    }



    public function hub(): View

    {

        $this->authorize('viewAny', \App\Models\Strategie\Vision::class);



        return view('strategie.hub', [

            'synthese' => $this->service->syntheseHub($this->alignement),

            'alignement' => $this->alignement->syntheseAlignement(),

            'programmation' => $this->alignement->syntheseProgrammation(),

            'visions' => \App\Models\Strategie\Vision::orderByDesc('date_debut')->limit(5)->get(),

            'plans' => \App\Models\Strategie\PlanStrategique::with('vision')->orderByDesc('date_debut')->limit(5)->get(),

        ]);

    }

}


