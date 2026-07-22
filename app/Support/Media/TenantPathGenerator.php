<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

final class TenantPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->base($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->base($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->base($media).'/responsive-images/';
    }

    private function base(Media $media): string
    {
        $tenantId = (string) $media->getAttribute('tenant_id');
        if ($tenantId === '') {
            throw new \LogicException('Media requires a tenant_id before path generation.');
        }

        return 'tenants/'.$tenantId.'/media/'.$media->getKey();
    }
}
