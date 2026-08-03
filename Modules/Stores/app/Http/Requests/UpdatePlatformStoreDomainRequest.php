<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Modules\Stores\Models\StoreDomain;

final class UpdatePlatformStoreDomainRequest extends PlatformStoreDomainWriteRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $domain = StoreDomain::query()
            ->where('public_id', (string) $this->route('domain'))
            ->first();

        return $this->domainRules(
            partial: true,
            ignoreDomainKey: $domain?->getKey(),
        );
    }
}
