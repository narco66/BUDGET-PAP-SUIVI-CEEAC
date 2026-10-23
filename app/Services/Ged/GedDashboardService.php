<?php

namespace App\Services\Ged;

use App\Enums\Ged\DocumentStatut;
use App\Enums\Ged\NiveauConfidentialite;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedJournal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GedDashboardService
{
    public function __construct(private GedDocumentService $documents) {}

    /** @return array<string, mixed> */
    public function indicateurs(?User $user = null): array
    {
        $base = GedDocument::query();
        if ($user) {
            $base = $this->documents->scopeAccessible($base, $user);
        }

        $parStatut = (clone $base)->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')->pluck('total', 'statut');

        $parModule = (clone $base)->select('module_source', DB::raw('count(*) as total'))
            ->whereNotNull('module_source')->groupBy('module_source')->pluck('total', 'module_source');

        return [
            'total' => (clone $base)->count(),
            'deposes' => (clone $base)->where('statut', DocumentStatut::Depose)->count(),
            'valides' => (clone $base)->where('statut', DocumentStatut::Valide)->count(),
            'signes' => (clone $base)->where('est_signe', true)->count(),
            'archives' => (clone $base)->where('est_archive', true)->count(),
            'confidentiels' => (clone $base)->whereIn('niveau_confidentialite', [
                NiveauConfidentialite::Confidentiel,
                NiveauConfidentialite::TresConfidentiel,
            ])->count(),
            'expires' => (clone $base)->where('statut', DocumentStatut::Expire)->count(),
            'en_validation' => (clone $base)->where('statut', DocumentStatut::EnValidation)->count(),
            'en_attente_signature' => (clone $base)->where('signature_status', 'en_attente')->count(),
            'volume_octets' => (clone $base)->sum('taille'),
            'par_statut' => $parStatut,
            'par_module' => $parModule,
            'dernieres_activites' => GedJournal::with(['document', 'utilisateur'])
                ->latest()->limit(10)->get(),
            'recents' => (clone $base)->with('createur')->latest('date_depot')->limit(5)->get(),
        ];
    }
}
