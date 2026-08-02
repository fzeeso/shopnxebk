<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Themes\Models\ThemeVersion;

/** @extends JsonResource<ThemeVersion> */
final class ThemeVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'version' => $this->version,
            'status' => $this->resource->statusValue(),
            'engine_version' => $this->engine_version,
            'minimum_platform_version' => $this->minimum_platform_version,
            'maximum_platform_version' => $this->maximum_platform_version,
            'source_archive_object_key' => $this->source_archive_object_key,
            'compiled_artifact_object_key' => $this->compiled_artifact_object_key,
            'package_sha256' => $this->package_sha256,
            'package_size_bytes' => $this->package_size_bytes,
            'uncompressed_size_bytes' => $this->uncompressed_size_bytes,
            'file_count' => $this->file_count,
            'manifest' => $this->manifest,
            'settings_schema' => $this->settings_schema,
            'validation_report' => $this->validation_report,
            'release_notes' => $this->release_notes,
            'uploaded_by_user_id' => $this->whenLoaded('uploader', fn () => $this->uploader?->public_id),
            'approved_by_user_id' => $this->whenLoaded('approver', fn () => $this->approver?->public_id),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'submissions' => ThemeSubmissionResource::collection($this->whenLoaded('submissions')),
        ];
    }
}
