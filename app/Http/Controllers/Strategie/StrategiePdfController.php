<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Services\Reporting\GouvernanceExportService;
use App\Services\Strategie\StrategieAlignementService;
use App\Services\Strategie\StrategieCoherenceService;
use App\Services\Strategie\StrategieReferentielService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StrategiePdfController extends Controller
{
    /** Rapports PDF archivables → libellé du rapport gouverné + base de nom de fichier. */
    private const RAPPORTS = [
        'synthese' => ['titre' => 'Synthèse de la planification stratégique', 'base' => 'synthese-planification-strategique'],
        'matrice' => ['titre' => "Matrice d'alignement stratégique", 'base' => 'matrice-alignement'],
        'coherence' => ['titre' => 'Rapport de cohérence stratégique', 'base' => 'rapport-coherence-strategique'],
    ];

    public function __construct(
        private readonly StrategieReferentielService $service,
        private readonly StrategieAlignementService $alignement,
        private readonly StrategieCoherenceService $coherence,
        private readonly GouvernanceExportService $gouvernance,
    ) {
    }

    public function synthese(Request $request): Response
    {
        abort_unless($request->user()->can('strategie.export'), 403);

        return $this->telecharger('synthese', $request);
    }

    public function matrice(Request $request): Response
    {
        abort_unless($request->user()->can('strategie.export'), 403);

        return $this->telecharger('matrice', $request);
    }

    public function coherence(Request $request): Response
    {
        abort_unless($request->user()->can('strategie.export'), 403);

        return $this->telecharger('coherence', $request);
    }

    /**
     * Archive un rapport stratégique PDF comme rapport officiel gouverné (versioning, hash, QR, GED, audit),
     * sans altérer l'export rapide. Stratégie = hors exercice (rapport rattaché à l'année courante).
     */
    public function archiver(Request $request, string $rapport): RedirectResponse
    {
        abort_unless(isset(self::RAPPORTS[$rapport]), 404);
        abort_unless($request->user()->can('strategie.export'), 403);

        $r = $this->gouvernance->archiver(
            'export_strategie_'.$rapport.'_pdf',
            self::RAPPORTS[$rapport]['titre'],
            null,
            'pdf',
            $this->rendre($rapport, $request),
            $request->user(),
            array_filter(['plan' => $request->integer('plan') ?: null]),
        );

        return back()->with(
            'succes',
            "Rapport archivé comme rapport officiel {$r->reference} (v{$r->version}). Disponible au Centre d'exports.",
        );
    }

    private function telecharger(string $rapport, Request $request): Response
    {
        $nom = self::RAPPORTS[$rapport]['base'].'-'.now()->format('Ymd').'.pdf';

        return response($this->rendre($rapport, $request), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
        ]);
    }

    /** Produit le binaire PDF d'un rapport stratégique (source unique téléchargement / archivage). */
    private function rendre(string $rapport, Request $request): string
    {
        $planId = $request->integer('plan') ?: null;

        return match ($rapport) {
            'synthese' => Pdf::loadView('strategie.pdf.synthese', [
                'synthese' => $this->service->syntheseHub($this->alignement),
                'plans' => $this->service->cataloguePlans(),
                'programmes' => $this->service->catalogueProgrammes(),
                'projets' => $this->service->catalogueProjets(),
            ])->setPaper('a4', 'portrait')->output(),
            'matrice' => Pdf::loadView('strategie.pdf.matrice', [
                'lignes' => $this->alignement->matriceAlignement($planId),
                'synthese' => $this->alignement->syntheseAlignement(),
            ])->setPaper('a4', 'landscape')->output(),
            'coherence' => Pdf::loadView('strategie.pdf.coherence', [
                'rapport' => $this->coherence->rapport($planId),
            ])->setPaper('a4', 'portrait')->output(),
        };
    }
}
