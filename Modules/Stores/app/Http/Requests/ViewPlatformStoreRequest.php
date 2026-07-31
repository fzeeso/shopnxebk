<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

final class ViewPlatformStoreRequest extends PlatformStoreRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
