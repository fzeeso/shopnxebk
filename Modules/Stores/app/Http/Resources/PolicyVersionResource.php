<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Stores\Models\PolicyVersion;

/** @extends JsonResource<PolicyVersion> */
final class PolicyVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'language' => $this->whenLoaded('language', fn (): array => [
                'id' => $this->language->public_id,
                'name' => $this->language->name,
                'lang_icon' => $this->language->langIconUrl(),
                'lang_image' => $this->language->langImageUrl(),
                'locale' => $this->language->locale,
            ]),
            'version' => $this->version,
            'content' => $this->content,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->public_id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
