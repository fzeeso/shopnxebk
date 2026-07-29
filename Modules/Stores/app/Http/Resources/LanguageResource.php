<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Enums\LanguageDirection;
use Modules\Stores\Models\Language;

/** @extends JsonResource<Language> */
final class LanguageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'locale' => $this->locale,
            'direction' => $this->direction instanceof LanguageDirection
                ? $this->direction->value
                : (string) $this->direction,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
