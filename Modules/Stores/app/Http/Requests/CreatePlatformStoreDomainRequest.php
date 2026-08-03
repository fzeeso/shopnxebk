<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

final class CreatePlatformStoreDomainRequest extends PlatformStoreDomainWriteRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return $this->domainRules(partial: false);
    }
}
