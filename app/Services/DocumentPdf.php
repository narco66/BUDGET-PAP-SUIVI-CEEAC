<?php

namespace App\Services;

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\PieceJustificative;
use App\Services\Reporting\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Génère les documents institutionnels PDF (D7/§7) et les archive en GED. */
class DocumentPdf
{
    public function engagement(Engagement $e): PdfInstance
    {
        $e->load('imputations.ligneBudgetaire.rubrique', 'imputations.ligneBudgetaire.structure', 'createur', 'controleur', 'exercice');

        return Pdf::loadView('pdf.engagement', array_merge(['engagement' => $e], $this->qr('engagement', $e)));
    }

    public function certificatReservation(Engagement $e): PdfInstance
    {
        $e->load('imputations.ligneBudgetaire', 'controleur', 'createur', 'exercice');

        return Pdf::loadView('pdf.certificat_reservation', array_merge(['engagement' => $e], $this->qr('engagement', $e)));
    }

    public function besoin(ExpressionBesoin $b): PdfInstance
    {
        $b->load('structure', 'demandeur', 'activite', 'exercice');

        return Pdf::loadView('pdf.besoin', array_merge(['besoin' => $b], $this->qr('besoin', $b)));
    }

    /** Document d'une étape aval (liquidation / ordonnancement / paiement). */
    public function etapeDepense(Model $etape, array $config): PdfInstance
    {
        $engagement = $etape->engagementRacine()
            ->load('imputations.ligneBudgetaire.rubrique', 'imputations.ligneBudgetaire.structure', 'exercice');

        return Pdf::loadView('pdf.etape_depense', array_merge([
            'etape' => $etape, 'config' => $config, 'engagement' => $engagement,
        ], $this->qr($config['permission'] ?? 'etape', $etape)));
    }

    /**
     * Génère le PDF d'un gabarit et l'archive comme pièce justificative rattachée à $objet.
     */
    public function archiver(Model $objet, string $methode, string $nom, ?int $userId = null): PieceJustificative
    {
        /** @var PdfInstance $pdf */
        $pdf = $this->{$methode}($objet);

        $chemin = 'ged/'.now()->format('Y/m').'/'.$nom;
        Storage::disk('local')->put($chemin, $pdf->output());

        return $objet->pieces()->create([
            'nom' => $nom,
            'chemin' => $chemin,
            'type_mime' => 'application/pdf',
            'taille' => Storage::disk('local')->size($chemin),
            'categorie' => 'document_genere',
            'televerse_par' => $userId,
        ]);
    }

    public static function urlVerification(string $type, Model $objet): string
    {
        $spa = rtrim((string) config('spa.app_url', ''), '/');
        if ($spa !== '') {
            return $spa.'/verification/'.$type.'/'.$objet->getKey();
        }

        return route('depense.verifier', ['type' => $type, 'id' => $objet->getKey()]);
    }

    /** @return array{qr_data_uri:?string,qr_url:string} */
    private function qr(string $type, Model $objet): array
    {
        $url = self::urlVerification($type, $objet);

        return [
            'qr_data_uri' => app(QrCodeService::class)->dataUri($url),
            'qr_url' => $url,
        ];
    }
}
