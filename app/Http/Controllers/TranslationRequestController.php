<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TranslationRequestResource;
use App\Models\TranslationRequest;
use Illuminate\Http\JsonResponse;

final class TranslationRequestController extends Controller
{
    public function show(string $translationRequest): JsonResponse
    {
        $request = TranslationRequest::query()
            ->where('public_id', $translationRequest)
            ->firstOrFail();

        return response()->json([
            'data' => new TranslationRequestResource($request),
        ]);
    }
}
