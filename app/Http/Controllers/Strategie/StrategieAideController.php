<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Models\Strategie\Vision;
use App\Services\Strategie\StrategieManuelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StrategieAideController extends Controller
{
    public function __construct(
        private readonly StrategieManuelService $manuel,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Vision::class);

        return view('strategie.aide.index', [
            'titre' => $this->manuel->titre(),
            'corps' => $this->manuel->corpsHtml(),
        ]);
    }

    public function pdf(): Response
    {
        $this->authorize('viewAny', Vision::class);

        $corps = $this->manuel->corpsHtml();
        $pdf = Pdf::loadHTML($this->manuel->enveloppePdf($corps))
            ->setPaper('a4', 'portrait');

        return $pdf->download('manuel-planification-strategique-'.now()->format('Ymd').'.pdf');
    }

    public function word(): Response
    {
        $this->authorize('viewAny', Vision::class);

        $contenu = $this->manuel->contenuWord();

        return response($contenu, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="manuel-planification-strategique-'.now()->format('Ymd').'.docx"',
        ]);
    }
}
