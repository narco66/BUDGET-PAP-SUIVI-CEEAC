<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DepenseExportController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Exports Excel/CSV de la chaîne — mêmes jeux que Blade, session Sanctum. */
class DepenseExportApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->autorise($request), 403);

        $jeux = [];
        foreach (DepenseExportController::catalogue() as $cle => $meta) {
            $jeux[] = [
                'cle' => $cle,
                'libelle' => $meta['titre'],
            ];
        }

        return response()->json(['data' => $jeux]);
    }

    public function telecharger(Request $request, string $cle, DepenseExportController $export): BinaryFileResponse
    {
        return $export->telechargerPourApi($request, $cle);
    }

    private function autorise(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->can('depenses.export') || $user->can('depenses.view'));
    }
}
