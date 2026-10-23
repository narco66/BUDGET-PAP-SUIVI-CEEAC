<?php

namespace App\Http\Controllers;

use App\Exports\PapListeExport;
use App\Models\Pap;
use App\Support\PapPermissionAliases;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PapExportController extends Controller
{
    public function liste(): BinaryFileResponse
    {
        $this->authorize('viewAny', Pap::class);
        abort_unless(
            auth()->user()->can('pap.exporter')
            || auth()->user()->can('pap.modifier')
            || PapPermissionAliases::peut(auth()->user(), 'export'),
            403,
        );

        $filename = 'paps-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new PapListeExport, $filename);
    }
}
