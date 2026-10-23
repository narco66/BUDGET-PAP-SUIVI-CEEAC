<?php



namespace App\Services\Depense;



use App\Models\OrdrePaiement;

use App\Models\Ordonnancement;

use App\Models\User;

use App\Support\Numero;

use Illuminate\Support\Facades\Auth;



/** Émission d'un ordre de paiement distinct de l'ordonnancement (phase D2). */

class OrdrePaiementService

{

    public function emettre(Ordonnancement $ordonnancement, ?User $emisPar = null): OrdrePaiement

    {

        $existant = $ordonnancement->ordresPaiement()->where('statut', 'emis')->first();

        if ($existant) {

            return $existant;

        }



        $engagement = $ordonnancement->liquidation->engagement;

        $annee = $engagement->exercice?->annee ?? (int) date('Y');



        return $ordonnancement->ordresPaiement()->create([

            'numero' => Numero::suivant('ordres_paiement', 'OP', $annee),

            'montant' => (float) $ordonnancement->montant,

            'date_ordre' => now()->toDateString(),

            'statut' => 'emis',

            'emis_par' => ($emisPar ?? Auth::user())?->id,

            'emis_le' => now(),

        ]);

    }

}


