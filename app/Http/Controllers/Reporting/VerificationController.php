<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\Rapport;
use App\Services\Reporting\SignatureService;
use Illuminate\View\View;

/** Vérification publique d'authenticité d'un rapport via son jeton QR (sans authentification). */
class VerificationController extends Controller
{
    public function __construct(private readonly SignatureService $signature)
    {
    }

    public function show(string $token): View
    {
        $rapport = Rapport::with(['generePar', 'signePar', 'exercice'])->where('qr_token', $token)->first();

        $signatureValide = $rapport ? $this->signature->verifier($rapport) : false;

        return view('reporting.verification', [
            'rapport' => $rapport,
            'authentique' => (bool) $rapport,
            'signatureValide' => $signatureValide,
        ]);
    }
}
