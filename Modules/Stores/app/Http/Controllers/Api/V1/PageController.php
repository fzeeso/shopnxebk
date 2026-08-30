<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Stores\Http\Requests\CreatePageRequest;
use Modules\Stores\Http\Requests\ListPagesRequest;
use Modules\Stores\Http\Requests\UpdatePageRequest;
use Modules\Stores\Http\Resources\PageResource;
use Modules\Stores\Models\Page;
use Modules\Stores\Services\PageManagementService;

final class PageController extends Controller
{
    public function index(ListPagesRequest $request, PageManagementService $service): JsonResponse
    {
        return PageResource::collection(
            $service->list($this->user($request), $request->validated()),
        )->response();
    }

    public function store(CreatePageRequest $request, PageManagementService $service): JsonResponse
    {
        return response()->json(['data' => new PageResource(
            $service->create($this->user($request), $request->validated()),
        )], 201);
    }

    public function show(Request $request, Page $page, PageManagementService $service): JsonResponse
    {
        return response()->json(['data' => new PageResource(
            $service->show($this->user($request), $page),
        )]);
    }

    public function update(UpdatePageRequest $request, Page $page, PageManagementService $service): JsonResponse
    {
        return response()->json(['data' => new PageResource(
            $service->update($this->user($request), $page, $request->validated()),
        )]);
    }

    public function publish(Request $request, Page $page, PageManagementService $service): JsonResponse
    {
        return $this->pageResponse($service->publish($this->user($request), $page));
    }

    public function unpublish(Request $request, Page $page, PageManagementService $service): JsonResponse
    {
        return $this->pageResponse($service->unpublish($this->user($request), $page));
    }

    public function enable(Request $request, Page $page, PageManagementService $service): JsonResponse
    {
        return $this->pageResponse($service->enable($this->user($request), $page));
    }

    public function disable(Request $request, Page $page, PageManagementService $service): JsonResponse
    {
        return $this->pageResponse($service->disable($this->user($request), $page));
    }

    public function destroy(Request $request, Page $page, PageManagementService $service): JsonResponse
    {
        $service->delete($this->user($request), $page);

        return response()->json(null, 204);
    }

    private function pageResponse(Page $page): JsonResponse
    {
        return response()->json(['data' => new PageResource($page)]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
