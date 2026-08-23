<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Resources\FulfillmentTypeResource;
use Modules\Catalog\Models\FulfillmentType;

final class FulfillmentTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $fulfillmentTypes = FulfillmentType::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return FulfillmentTypeResource::collection($fulfillmentTypes);
    }
}
