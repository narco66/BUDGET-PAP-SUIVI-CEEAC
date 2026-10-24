<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Navigation\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationApiController extends Controller
{
    public function show(Request $request, MenuService $menu): JsonResponse
    {
        return response()->json([
            'data' => [
                'items' => $menu->buildSpa($request->user()),
                'legacy_url' => config('spa.legacy_url'),
            ],
        ]);
    }
}
