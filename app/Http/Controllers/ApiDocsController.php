<?php

namespace App\Http\Controllers;

use App\Services\Interop\OpenApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Documentation interactive OpenAPI / Swagger UI (I-P3). */
class ApiDocsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('api.view') || $request->user()->can('api.admin'), 403);

        return view('api.docs', [
            'specUrl' => route('api.openapi.v1'),
        ]);
    }

    public function spec(OpenApiService $openApi): JsonResponse
    {
        abort_unless(auth()->user()?->can('api.view') || auth()->user()?->can('api.admin'), 403);

        return response()->json($openApi->specification());
    }
}
