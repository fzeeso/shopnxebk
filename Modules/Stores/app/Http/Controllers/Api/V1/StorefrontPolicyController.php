<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Stores\Services\StorefrontPolicyService;

final class StorefrontPolicyController extends Controller
{
    public function index(Request $request, StorefrontPolicyService $service): JsonResponse
    {
        return response()->json(['data' => $service->list($request->string('locale')->toString() ?: null)]);
    }

    public function show(Request $request, string $slug, StorefrontPolicyService $service): JsonResponse
    {
        return response()->json(['data' => $service->show($slug, $request->string('locale')->toString() ?: null)]);
    }
}
