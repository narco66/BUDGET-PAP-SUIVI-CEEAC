<?php

namespace App\Services\Reporting;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Orchestre la génération complète d'un rapport : composition (source unique) → rendu du format
 * demandé → calcul d'empreinte SHA-256 → archivage disque → enregistrement traçable (versioning, QR).
 */
class RapportRenderer
{
    /** Orientation paysage pour les rapports à larges tableaux. */
    private const PAYSAGE = [
        'execution_axe', 'bailleur', 'categorie', 'rbm_gar', 'trimestriel', 'semestriel', 'annuel',
        'performance_pilier', 'performance_axe', 'performance_departement', 'recettes_mobilisation', 'recettes_financier',
        'tresorerie_flux', 'tresorerie_dette_fournisseurs', 'immobilisations', 'personnalise',
        'technique_sante', 'technique_integrite', 'technique_audit',
        'ecarts_physique_financier', 'ipsas_etat_financier',
    ];

    private const MIMES = [
        'pdf' => ['application/pdf', 'pdf'],
        'excel' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'word' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
        'csv' => ['text/csv', 'csv'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx'],
    ];

    public function __construct(
        private readonly RapportComposer $composer,
        private readonly RapportPeriodeService $periodes,
        private readonly QrCodeService $qr,
        private readonly WordRenderer $word,
        private readonly PptxRenderer $pptx,
        private readonly RapportWorkflowService $workflow,
        private readonly RapportIpsasService $ipsas,
        private readonly RapportAuditService $audit,
    ) {
    }

    /**
     * @return array{rapport:Rapport, contenu:string, mime:string, filename:string}
     */
    public function generer(string $type, Exercice $exercice, string $format, array $params, User $user): array
    {
        abort_unless(isset(self::MIMES[$format]), 422, "Format non supporté : {$format}");

        $periode = $this->periodes->resoudre($exercice, $params['periode'] ?? null);
        $composed = $this->composer->composer($type, $exercice, $params);
        [$mime, $ext] = self::MIMES[$format];

        $checksumSources = hash('sha256', json_encode($composed['sections'], JSON_THROW_ON_ERROR));

        $token = Str::random(48);
        $reference = sprintf('RPT-%d-%06d', $exercice->annee, (int) (Rapport::max('id') ?? 0) + 1);
        $version = Rapport::prochaineVersion($type, $exercice->id, $periode['periode'], $format);
        $urlVerif = route('verification.rapport', $token);
        $qrDataUri = $this->qr->dataUri($urlVerif);

        $meta = [
            'reference' => $reference, 'version' => $version,
            'date' => now()->format('d/m/Y H:i'), 'auteur' => $user->name,
            'periode' => $periode['periode'],
            'verification' => $urlVerif, 'qr' => $qrDataUri,
            'signataire' => null, 'signe_le' => null,
        ];

        $contenu = match ($format) {
            'pdf' => Pdf::loadView('reporting.pdf.document', [
                'c' => $composed,
                'meta' => $meta,
                'charte' => \App\Support\CharteCeeac::export(),
            ])
                ->setPaper('a4', in_array($type, self::PAYSAGE, true) ? 'landscape' : 'portrait')->output(),
            'excel' => Excel::raw(new \App\Exports\Reporting\RapportExport($composed, $meta), ExcelFormat::XLSX),
            'csv' => Excel::raw(new \App\Exports\Reporting\RapportCsv($composed), ExcelFormat::CSV),
            'word' => $this->word->rendre($composed, $meta, $qrDataUri),
            'pptx' => $this->pptx->rendre($composed, $meta, $qrDataUri),
        };

        $chemin = "rapports/{$exercice->annee}/{$reference}_v{$version}.{$ext}";
        Storage::disk('local')->put($chemin, $contenu);

        $modele = $this->workflow->resoudreModele($type);

        $rapport = Rapport::create([
            'reference' => $reference,
            'type' => $type,
            'titre' => $composed['titre'],
            'exercice_id' => $exercice->id,
            'modele_id' => $modele?->id,
            'periode' => $periode['periode'],
            'periode_type' => $periode['periode_type'],
            'periode_debut' => $periode['periode_debut'],
            'periode_fin' => $periode['periode_fin'],
            'format' => $format,
            'parametres' => $params,
            'chemin' => $chemin,
            'taille' => strlen($contenu),
            'hash' => hash('sha256', $contenu),
            'checksum_sources' => $checksumSources,
            'qr_token' => $token,
            'version' => $version,
            'statut' => 'genere',
            'statut_workflow' => 'genere',
            'verrouille' => false,
            'ged_indexe' => false,
            'genere_par' => $user->id,
        ]);

        $this->workflow->indexerGedSiBesoin($rapport, $user);
        $rapport->refresh();

        if ($type === 'ipsas_etat_financier') {
            $this->ipsas->enregistrerSnapshot($rapport, $exercice);
        }

        $this->audit->journaliser('generate', $rapport, $user, request(), [
            'format' => $format,
            'periode' => $periode['periode'],
        ]);

        app(\App\Services\Notifications\ReportingNotificationService::class)
            ->notifierRapportGenere($rapport, $user);

        return [
            'rapport' => $rapport,
            'contenu' => $contenu,
            'mime' => $mime,
            'filename' => "{$reference}_{$type}.{$ext}",
        ];
    }
}
