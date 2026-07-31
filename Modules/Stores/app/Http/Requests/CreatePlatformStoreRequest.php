<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

final class CreatePlatformStoreRequest extends PlatformStoreWriteRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return $this->storeRules(storeKey: null, partial: false);
    }
}
