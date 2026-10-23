<?php

namespace App\Http\Controllers\Rbm;

use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Models\Indicateur;
use App\Models\SourceVerification;
use App\Services\Ged\GedAttachmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Dossier GED rattaché à un nœud RBM (phase E8). */
class RbmGedController extends Controller
{
    /** @var array<string, class-string> */
    private const TYPES = [
        'activite' => Activite::class,
        'indicateur' => Indicateur::class,
        'source_verification' => SourceVerification::class,
    ];

    public function show(Request $request, string $type, int $id): View
    {
        abort_unless($request->user()->can('ged.voir'), 403);
        abort_unless(isset(self::TYPES[$type]), 404);

        $attachable = self::TYPES[$type]::findOrFail($id);

        return view('rbm.ged', [
            'type' => $type,
            'attachable' => $attachable,
            'label' => GedAttachmentService::MODULE_LABELS[$type] ?? strtoupper($type),
        ]);
    }
}
