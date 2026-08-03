<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class PlatformStoreDomainWriteRequest extends PlatformStoreRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('domain') && $this->input('domain') !== null) {
            $this->merge([
                'domain' => Str::lower(trim((string) $this->input('domain'), " .\t\n\r\0\x0B")),
            ]);
        }
    }

    /** @return array<string, list<mixed>> */
    protected function domainRules(bool $partial, ?int $ignoreDomainKey = null): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $domainUnique = Rule::unique('store_domains', 'domain');
        if ($ignoreDomainKey !== null) {
            $domainUnique->ignore($ignoreDomainKey);
        }

        return [
            'domain' => [$required, 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $domainUnique],
            'domain_type' => ['sometimes', Rule::in(['platform', 'custom'])],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['pending', 'active', 'failed', 'disabled'])],
            'ssl_status' => ['sometimes', Rule::in(['pending', 'provisioning', 'active', 'failed'])],
            'is_verified' => ['sometimes', 'boolean'],
        ];
    }
}
