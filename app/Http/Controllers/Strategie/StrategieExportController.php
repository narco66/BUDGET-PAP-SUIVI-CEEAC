<?php

namespace App\Http\Controllers\Strategie;

use App\Exports\Strategie\StrategieCatalogueExport;
use App\Http\Controllers\Controller;
use App\Services\Reporting\GouvernanceExportService;
use App\Services\Strategie\StrategieAlignementService;
use App\Services\Strategie\StrategieReferentielService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StrategieExportController extends Controller
{
    /** Catalogues archivables → libellé du rapport gouverné. */
    private const CATALOGUES = [
        'visions' => 'Catalogue des visions stratégiques',
        'plans' => 'Catalogue des plans stratégiques',
        'orientations' => 'Catalogue des orientations stratégiques',
        'programmes' => 'Catalogue des programmes stratégiques',
        'projets' => 'Catalogue des projets stratégiques',
        'portefeuilles' => 'Portefeuilles de projets',
        'matrice' => "Matrice d'alignement stratégique",
        'objectifs' => 'Catalogue des objectifs stratégiques',
        'indicateurs' => 'Catalogue des indicateurs stratégiques',
        'risques' => 'Catalogue des risques stratégiques',
    ];

    public function __construct(
        private readonly StrategieReferentielService $service,
        private readonly StrategieAlignementService $alignement,
        private readonly GouvernanceExportService $gouvernance,
    ) {
    }

    public function excel(Request $request, string $catalogue): BinaryFileResponse
    {
        abort_unless($request->user()->can('strategie.export'), 403);

        [$rows, $headings, $filename] = $this->jeu($catalogue, $request);

        return Excel::download(
            new StrategieCatalogueExport($rows, $headings),
            $filename.'-'.now()->format('Ymd').'.xlsx'
        );
    }

    /**
     * Archive un catalogue stratégique comme rapport officiel gouverné (versioning, hash, QR, GED, audit),
     * sans altérer l'export rapide. Stratégie = hors exercice (rapport rattaché à l'année courante).
     */
    public function archiver(Request $request, string $catalogue): RedirectResponse
    {
        abort_unless(isset(self::CATALOGUES[$catalogue]), 404);
        abort_unless($request->user()->can('strategie.export'), 403);

        [$rows, $headings] = $this->jeu($catalogue, $request);
        $contenu = Excel::raw(new StrategieCatalogueExport($rows, $headings), ExcelFormat::XLSX);

        $rapport = $this->gouvernance->archiver(
            'export_strategie_'.$catalogue,
            self::CATALOGUES[$catalogue],
            null,
            'excel',
            $contenu,
            $request->user(),
            array_filter(['plan' => $request->integer('plan') ?: null]),
        );

        return back()->with(
            'succes',
            "Catalogue archivé comme rapport officiel {$rapport->reference} (v{$rapport->version}). Disponible au Centre d'exports.",
        );
    }

    /**
     * Construit les lignes, en-têtes et base de nom de fichier d'un catalogue.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: list<string>, 2: string}
     */
    private function jeu(string $catalogue, Request $request): array
    {
        return match ($catalogue) {
            'visions' => [
                $this->service->catalogueVisions()->map(fn ($v) => [
                    $v->reference, $v->intitule, $v->periode,
                    optional($v->date_debut)?->format('Y-m-d'),
                    optional($v->date_fin)?->format('Y-m-d'),
                    $v->statut, $v->version,
                ]),
                ['Référence', 'Intitulé', 'Période', 'Début', 'Fin', 'Statut', 'Version'],
                'visions-strategiques',
            ],
            'plans' => [
                $this->service->cataloguePlans()->map(fn ($p) => [
                    $p->reference, $p->intitule, $p->vision?->reference,
                    optional($p->date_debut)?->format('Y-m-d'),
                    optional($p->date_fin)?->format('Y-m-d'),
                    $p->statut, $p->version,
                ]),
                ['Référence', 'Intitulé', 'Vision', 'Début', 'Fin', 'Statut', 'Version'],
                'plans-strategiques',
            ],
            'orientations' => [
                $this->service->catalogueOrientations()->map(fn ($o) => [
                    $o->code, $o->intitule, $o->planStrategique?->reference, $o->ordre, $o->statut,
                ]),
                ['Code', 'Intitulé', 'Plan', 'Ordre', 'Statut'],
                'orientations-strategiques',
            ],
            'programmes' => [
                $this->service->catalogueProgrammes()->map(fn ($p) => [
                    $p->code, $p->intitule, $p->orientationStrategique?->code,
                    $p->budget_previsionnel,
                    optional($p->date_debut)?->format('Y-m-d'),
                    optional($p->date_fin)?->format('Y-m-d'),
                    $p->statut,
                ]),
                ['Code', 'Intitulé', 'Orientation', 'Budget prév.', 'Début', 'Fin', 'Statut'],
                'programmes-strategiques',
            ],
            'projets' => [
                $this->service->catalogueProjets()->map(fn ($p) => [
                    $p->code, $p->intitule, $p->programmeStrategique?->code,
                    $p->statut, $p->cycle_vie, $p->budget_previsionnel,
                    $p->budget_engage, $p->budget_consomme,
                ]),
                ['Code', 'Intitulé', 'Programme', 'Statut', 'Cycle', 'Budget prév.', 'Engagé', 'Consommé'],
                'projets-strategiques',
            ],
            'portefeuilles' => [
                $this->service->cataloguePortefeuilles()->map(fn ($p) => [
                    $p->reference, $p->intitule, $p->projets_count, $p->statut,
                ]),
                ['Référence', 'Intitulé', 'Nb projets', 'Statut'],
                'portefeuilles-projets',
            ],
            'matrice' => [
                $this->alignement->matriceAlignement($request->integer('plan') ?: null)->map(fn ($l) => [
                    $l['vision'], $l['orientation'], $l['programme'], $l['projet'], $l['activite'],
                    $l['intitule'], $l['pilier_rbm'], $l['budget'], $l['aligne'] ? 'Oui' : 'Non',
                ]),
                ['Vision', 'Orientation', 'Programme', 'Projet', 'Activité', 'Intitulé', 'Pilier RBM', 'Budget', 'Aligné'],
                'matrice-alignement-strategique',
            ],
            'objectifs' => [
                $this->service->catalogueObjectifs()->map(fn ($o) => [
                    $o->code, $o->intitule, $o->planStrategique?->reference, $o->orientationStrategique?->code,
                    $o->indicateurs_count, $o->ordre, $o->statut,
                ]),
                ['Code', 'Intitulé', 'Plan', 'Orientation', 'Nb indic.', 'Ordre', 'Statut'],
                'objectifs-strategiques',
            ],
            'indicateurs' => [
                $this->service->catalogueIndicateurs()->map(fn ($i) => [
                    $i->code, $i->libelle, $i->objectifStrategique?->code, $i->unite, $i->periodicite,
                    $i->valeur_reference, $i->valeur_cible, $i->seuil_alerte, $i->statut,
                ]),
                ['Code', 'Libellé', 'Objectif', 'Unité', 'Périodicité', 'Réf.', 'Cible', 'Seuil', 'Statut'],
                'indicateurs-strategiques',
            ],
            'risques' => [
                $this->service->catalogueRisques()->map(fn ($r) => [
                    $r->code, Str::limit($r->description, 80), $r->planStrategique?->reference,
                    $r->probabilite, $r->impact, $r->statut,
                ]),
                ['Code', 'Description', 'Plan', 'Probabilité', 'Impact', 'Statut'],
                'risques-strategiques',
            ],
            default => abort(404),
        };
    }
}
