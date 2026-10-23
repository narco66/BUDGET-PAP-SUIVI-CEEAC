<?php

namespace App\Http\Controllers;

use App\Exports\Depense\BesoinsExport;
use App\Exports\Depense\EcrituresComptablesExport;
use App\Exports\Depense\JournalConsolideExport;
use App\Exports\Depense\JournalEngagementsExport;
use App\Exports\Depense\JournalLiquidationsExport;
use App\Exports\Depense\JournalOrdonnancementsExport;
use App\Exports\Depense\JournalPaiementsExport;
use App\Exports\Depense\RestesAEngagerExport;
use App\Exports\Depense\RestesAPayerExport;
use App\Models\EcritureComptable;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Paiement;
use App\Services\Budget\SituationBudgetaireService;
use App\Services\Depense\JournalConsolideService;
use App\Services\Reporting\GouvernanceExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Exports Excel/CSV de la chaîne de la dépense (phases D1–D3) + archivage gouverné (P0). */
class DepenseExportController extends Controller
{
    /**
     * Catalogue des jeux d'export archivables : clé d'URL → identifiant Rapport gouverné + libellé.
     *
     * @var array<string, array{type: string, titre: string}>
     */
    private const ARCHIVES = [
        'besoins' => ['type' => 'export_depense_besoins', 'titre' => 'Journal des expressions de besoin'],
        'engagements' => ['type' => 'export_depense_engagements', 'titre' => 'Journal des engagements'],
        'liquidations' => ['type' => 'export_depense_liquidations', 'titre' => 'Journal des liquidations'],
        'ordonnancements' => ['type' => 'export_depense_ordonnancements', 'titre' => 'Journal des ordonnancements'],
        'paiements' => ['type' => 'export_depense_paiements', 'titre' => 'Journal des paiements'],
        'restes-a-payer' => ['type' => 'export_depense_restes_a_payer', 'titre' => 'Restes à payer'],
        'restes-a-engager' => ['type' => 'export_depense_restes_a_engager', 'titre' => 'Restes à engager'],
        'consolide' => ['type' => 'export_depense_consolide', 'titre' => 'Journal consolidé de la dépense'],
        'ecritures' => ['type' => 'export_depense_ecritures', 'titre' => 'Écritures comptables'],
    ];

    public function __construct(
        private readonly JournalConsolideService $journaux,
        private readonly SituationBudgetaireService $situations,
        private readonly GouvernanceExportService $gouvernance,
    ) {
    }

    public function besoins(Request $request): BinaryFileResponse
    {
        return $this->telechargerJeu('besoins', $request);
    }

    public function journalEngagements(Request $request): BinaryFileResponse
    {
        return $this->telechargerJeu('engagements', $request);
    }

    public function journalPaiements(Request $request): BinaryFileResponse
    {
        return $this->telechargerJeu('paiements', $request);
    }

    public function restesAPayer(Request $request): BinaryFileResponse
    {
        return $this->telechargerJeu('restes-a-payer', $request);
    }

    public function restesAEngager(Request $request): BinaryFileResponse
    {
        return $this->telechargerJeu('restes-a-engager', $request);
    }

    public function journalLiquidations(Request $request): BinaryFileResponse
    {
        return $this->telechargerJeu('liquidations', $request);
    }

    public function journalOrdonnancements(Request $request): BinaryFileResponse
    {
        return $this->telechargerJeu('ordonnancements', $request);
    }

    public function journalConsolide(Request $request): BinaryFileResponse
    {
        return $this->telechargerJeu('consolide', $request);
    }

    public function ecrituresComptables(Request $request): BinaryFileResponse
    {
        return $this->telechargerJeu('ecritures', $request);
    }

    /**
     * Archive un export comme rapport officiel gouverné (versioning, hash, QR, GED, audit),
     * sans altérer l'export rapide. Action opt-in déclenchée explicitement par l'utilisateur.
     */
    public function archiver(Request $request, string $cle): RedirectResponse
    {
        abort_unless(array_key_exists($cle, self::ARCHIVES), 404);
        abort_unless($request->user()->can('depenses.export'), 403);

        $exercice = $this->exercice($request);
        [$writer, , $logique] = $this->format($request);
        $jeu = $this->jeu($cle, $request, $exercice);

        $contenu = Excel::raw($jeu['instance'], $writer);

        $rapport = $this->gouvernance->archiver(
            self::ARCHIVES[$cle]['type'],
            self::ARCHIVES[$cle]['titre'],
            $exercice,
            $logique,
            $contenu,
            $request->user(),
            array_filter([
                'exercice' => $exercice->annee,
                'tache_id' => $request->integer('tache_id') ?: null,
            ]),
        );

        return back()->with(
            'succes',
            "Export archivé comme rapport officiel {$rapport->reference} (v{$rapport->version}). Disponible au Centre d'exports.",
        );
    }

    /** Télécharge un jeu d'export — comportement historique strictement préservé. */
    private function telechargerJeu(string $cle, Request $request): BinaryFileResponse
    {
        $this->autoriserExport($request);

        $exercice = $this->exercice($request);
        [$writer, $ext] = $this->format($request);
        $jeu = $this->jeu($cle, $request, $exercice);

        return Excel::download($jeu['instance'], "{$jeu['base']}.{$ext}", $writer);
    }

    /**
     * Construit l'instance d'export et la base de nom de fichier pour un jeu donné.
     *
     * @return array{instance: object, base: string}
     */
    private function jeu(string $cle, Request $request, Exercice $exercice): array
    {
        return match ($cle) {
            'besoins' => [
                'instance' => new BesoinsExport($this->lignesBesoins($request, $exercice)),
                'base' => "besoins_{$exercice->annee}",
            ],
            'engagements' => [
                'instance' => new JournalEngagementsExport($this->lignesEngagements($request, $exercice)),
                'base' => "journal_engagements_{$exercice->annee}",
            ],
            'paiements' => [
                'instance' => new JournalPaiementsExport($this->lignesPaiements($exercice)),
                'base' => "journal_paiements_{$exercice->annee}",
            ],
            'restes-a-payer' => [
                'instance' => new RestesAPayerExport($this->lignesRestesAPayer($exercice)),
                'base' => "restes_a_payer_{$exercice->annee}",
            ],
            'restes-a-engager' => [
                'instance' => new RestesAEngagerExport($this->situations->restesAEngager($exercice->id)),
                'base' => "restes_a_engager_{$exercice->annee}",
            ],
            'liquidations' => [
                'instance' => new JournalLiquidationsExport($this->journaux->journalLiquidations($exercice)),
                'base' => "journal_liquidations_{$exercice->annee}",
            ],
            'ordonnancements' => [
                'instance' => new JournalOrdonnancementsExport($this->journaux->journalOrdonnancements($exercice)),
                'base' => "journal_ordonnancements_{$exercice->annee}",
            ],
            'consolide' => [
                'instance' => new JournalConsolideExport($this->journaux->journalConsolide($exercice)),
                'base' => "journal_consolide_{$exercice->annee}",
            ],
            'ecritures' => [
                'instance' => new EcrituresComptablesExport($this->lignesEcritures($exercice)),
                'base' => "ecritures_comptables_{$exercice->annee}",
            ],
        };
    }

    private function lignesBesoins(Request $request, Exercice $exercice): \Illuminate\Support\Collection
    {
        $query = ExpressionBesoin::with('structure', 'activite', 'tache', 'exercice')
            ->where('exercice_id', $exercice->id)
            ->orderBy('numero');

        if ($request->filled('tache_id')) {
            $query->where('tache_id', $request->integer('tache_id'));
        }

        return $query->get()->map(fn ($b) => [
            $b->numero,
            $exercice->annee,
            $b->structure?->sigle ?? $b->structure?->libelle,
            $b->activite?->code,
            $b->tache?->code,
            ExpressionBesoin::PRIORITES[$b->priorite] ?? $b->priorite,
            $b->objet,
            (float) $b->montant_estime,
            $b->statut,
            $b->date_besoin?->format('Y-m-d'),
        ]);
    }

    private function lignesEngagements(Request $request, Exercice $exercice): \Illuminate\Support\Collection
    {
        $query = Engagement::with('expressionBesoin', 'exercice', 'tache')
            ->where('exercice_id', $exercice->id)
            ->orderBy('numero');

        if ($request->filled('tache_id')) {
            $query->where('tache_id', $request->integer('tache_id'));
        }

        return $query->get()->map(fn ($e) => [
            $e->numero,
            $exercice->annee,
            $e->expressionBesoin?->numero,
            $e->tache?->code,
            $e->objet,
            $e->beneficiaire,
            (float) $e->montant_total,
            $e->statut,
            $e->date_engagement?->format('Y-m-d'),
            $e->vise_le?->format('Y-m-d H:i'),
        ]);
    }

    private function lignesPaiements(Exercice $exercice): \Illuminate\Support\Collection
    {
        return Paiement::with('ordonnancement.liquidation.engagement')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $exercice->id))
            ->orderBy('numero')
            ->get()
            ->map(function (Paiement $p) {
                $eng = $p->ordonnancement?->liquidation?->engagement;

                return [
                    $p->numero,
                    $eng?->numero,
                    $eng?->objet,
                    (float) $p->montant,
                    $p->mode_paiement,
                    $p->reference_bancaire,
                    $p->statut,
                    $p->date_paiement?->format('Y-m-d'),
                ];
            });
    }

    private function lignesRestesAPayer(Exercice $exercice): \Illuminate\Support\Collection
    {
        $ordonnance = DB::table('ordonnancements as o')
            ->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->leftJoin('expressions_besoin as eb', 'eb.id', '=', 'e.expression_besoin_id')
            ->leftJoin('structures as s', 's.id', '=', 'eb.structure_id')
            ->where('e.exercice_id', $exercice->id)
            ->where('o.statut', 'valide')
            ->whereNull('o.deleted_at')
            ->groupBy('e.id', 'e.numero', 'e.objet', 'e.beneficiaire', 'e.montant_total', 's.sigle', 's.libelle')
            ->selectRaw('e.id, e.numero, e.objet, e.beneficiaire, e.montant_total as engage,
                         COALESCE(s.sigle, s.libelle, "—") as structure,
                         COALESCE(SUM(o.montant),0) as ordonnance')
            ->get();

        $liquide = DB::table('liquidations as l')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->where('e.exercice_id', $exercice->id)
            ->where('l.statut', 'valide')
            ->whereNull('l.deleted_at')
            ->groupBy('e.id')
            ->selectRaw('e.id, COALESCE(SUM(l.montant),0) as liquide')
            ->pluck('liquide', 'e.id');

        $paye = DB::table('paiements as p')
            ->join('ordonnancements as o', 'o.id', '=', 'p.ordonnancement_id')
            ->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->where('e.exercice_id', $exercice->id)
            ->where('p.statut', 'valide')
            ->whereNull('p.deleted_at')
            ->groupBy('e.id')
            ->selectRaw('e.id, COALESCE(SUM(p.montant),0) as paye')
            ->pluck('paye', 'e.id');

        return $ordonnance->map(function ($r) use ($paye, $liquide) {
            $payeMontant = (float) ($paye[$r->id] ?? 0);
            $ordonnanceMontant = (float) $r->ordonnance;
            $reste = max(0, $ordonnanceMontant - $payeMontant);

            return [
                $r->numero,
                $r->objet,
                $r->structure,
                $r->beneficiaire ?? '—',
                (float) $r->engage,
                (float) ($liquide[$r->id] ?? 0),
                $ordonnanceMontant,
                $payeMontant,
                $reste,
            ];
        });
    }

    private function lignesEcritures(Exercice $exercice): \Illuminate\Support\Collection
    {
        return EcritureComptable::with('paiement', 'engagement')
            ->where('exercice_id', $exercice->id)
            ->orderBy('date_ecriture')
            ->orderBy('numero_piece')
            ->get()
            ->map(fn (EcritureComptable $e) => [
                $e->numero_piece,
                $e->journal,
                $e->date_ecriture?->format('Y-m-d'),
                $e->compte_debit,
                $e->compte_credit,
                (float) $e->montant,
                $e->paiement?->numero,
                $e->engagement?->numero,
                $e->libelle,
                $e->statut,
            ]);
    }

    /**
     * Résout le format demandé.
     *
     * @return array{0: string, 1: string, 2: string} [writer Maatwebsite, extension, format logique]
     */
    private function format(Request $request): array
    {
        return $request->input('format') === 'csv'
            ? [ExcelFormat::CSV, 'csv', 'csv']
            : [ExcelFormat::XLSX, 'xlsx', 'excel'];
    }

    private function exercice(Request $request): Exercice
    {
        $annee = (int) $request->input('exercice', date('Y'));

        return Exercice::where('annee', $annee)->firstOrFail();
    }

    private function autoriserExport(Request $request): void
    {
        abort_unless(
            $request->user()->can('depenses.export') || $request->user()->can('depenses.view'),
            403
        );
    }
}
