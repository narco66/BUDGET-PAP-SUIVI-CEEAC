<?php

namespace App\Http\Controllers\Admin\Interop;

use App\Exports\Interop\ApiInventaireExport;
use App\Http\Controllers\Controller;
use App\Services\Interop\ApiMonitoringService;
use App\Services\Interop\OpenApiService;
use App\Support\InteropAuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exports inventaire API et rapport sécurité (I-P3). */
class InteropExportController extends Controller
{
    public function inventaire(Request $request, OpenApiService $openApi, string $format): Response|StreamedResponse|BinaryFileResponse
    {
        abort_unless($request->user()->can('api.admin') || $request->user()->can('api.publish'), 403);

        InteropAuditLogger::enregistrer('export.inventaire_api', $request->user(), null, null, [
            'format' => $format,
        ], $request);

        $nom = 'inventaire-api-v1-'.now()->format('Ymd');

        return match ($format) {
            'csv' => $this->csvInventaire($openApi, $nom),
            'xlsx' => Excel::download(new ApiInventaireExport($openApi), "{$nom}.xlsx"),
            default => abort(404),
        };
    }

    public function rapportSecurite(Request $request, ApiMonitoringService $monitoring, string $format): Response|StreamedResponse
    {
        abort_unless($request->user()->can('api.admin') || $request->user()->can('api.monitor'), 403);

        $rapport = $monitoring->rapportSecurite();

        InteropAuditLogger::enregistrer('export.rapport_securite_api', $request->user(), null, null, [
            'format' => $format,
        ], $request);

        $nom = 'rapport-securite-api-'.now()->format('Ymd');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rapport) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Indicateur', 'Valeur'], ';');
                foreach ($rapport['kpis'] as $k => $v) {
                    fputcsv($out, [$k, $v], ';');
                }
                fputcsv($out, ['rejets_rate_limit', $rapport['rejets_rate_limit']], ';');
                fputcsv($out, ['apps_actives', $rapport['apps_actives']], ';');
                fputcsv($out, ['webhooks_actifs', $rapport['webhooks_actifs']], ';');
                fclose($out);
            }, "{$nom}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $pdf = Pdf::loadView('admin.interoperabilite.exports.rapport-securite-pdf', compact('rapport'));

        return $pdf->download("{$nom}.pdf");
    }

    private function csvInventaire(OpenApiService $openApi, string $nom): StreamedResponse
    {
        return response()->streamDownload(function () use ($openApi) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Méthode', 'Chemin', 'OperationId', 'Tags', 'Résumé'], ';');
            foreach ($openApi->inventaireEndpoints() as $row) {
                fputcsv($out, [$row['methode'], $row['chemin'], $row['operationId'], $row['tags'], $row['summary']], ';');
            }
            fclose($out);
        }, "{$nom}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
