<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BrandWriteRequest;
use App\Http\Requests\PaginatedIndexRequest;
use App\Models\Brand;
use App\Support\Media\BrandResource;
use App\Support\Media\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Authentication\Models\User;

final class BrandController extends Controller
{
    public function index(PaginatedIndexRequest $request, BrandService $service): JsonResponse
    {
        return BrandResource::collection($service->list($this->user($request), $request->perPage()))->response();
    }

    public function store(BrandWriteRequest $request, BrandService $service): JsonResponse
    {
        return response()->json(['data' => new BrandResource(
            $service->create($this->user($request), $request->validated()),
        )], 201);
    }

    public function show(Request $request, Brand $brand, BrandService $service): JsonResponse
    {
        return response()->json(['data' => new BrandResource(
            $service->show($this->user($request), $brand),
        )]);
    }

    public function update(BrandWriteRequest $request, Brand $brand, BrandService $service): JsonResponse
    {
        return response()->json(['data' => new BrandResource(
            $service->update($this->user($request), $brand, $request->validated()),
        )]);
    }

    public function destroy(Request $request, Brand $brand, BrandService $service): JsonResponse
    {
        $service->delete($this->user($request), $brand);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
