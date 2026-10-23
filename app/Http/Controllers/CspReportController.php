<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/** Collecte des rapports CSP (Report-Only) — P3-4. */
class CspReportController extends Controller
{
    public function store(Request $request): Response
    {
        $payload = $request->getContent();
        if ($payload === '') {
            return response('', 204);
        }

        $decoded = json_decode($payload, true);
        Log::channel('single')->warning('csp.violation', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'report' => $decoded['csp-report'] ?? $decoded ?? $payload,
        ]);

        return response('', 204);
    }
}
