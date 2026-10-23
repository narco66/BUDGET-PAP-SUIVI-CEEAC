<?php

namespace App\Services;

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\PieceJustificative;
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

        return Pdf::loadView('pdf.engagement', ['engagement' => $e]);
    }

    public function certificatReservation(Engagement $e): PdfInstance
    {
        $e->load('imputations.ligneBudgetaire', 'controleur', 'createur', 'exercice');

        return Pdf::loadView('pdf.certificat_reservation', ['engagement' => $e]);
    }

    public function besoin(ExpressionBesoin $b): PdfInstance
    {
        $b->load('structure', 'demandeur', 'activite', 'exercice');

        return Pdf::loadView('pdf.besoin', ['besoin' => $b]);
    }

    /** Document d'une étape aval (liquidation / ordonnancement / paiement). */
    public function etapeDepense(Model $etape, array $config): PdfInstance
    {
        $engagement = $etape->engagementRacine()
            ->load('imputations.ligneBudgetaire.rubrique', 'imputations.ligneBudgetaire.structure', 'exercice');

        return Pdf::loadView('pdf.etape_depense', [
            'etape' => $etape, 'config' => $config, 'engagement' => $engagement,
        ]);
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
}
