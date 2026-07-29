<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Authentication\Models\User;

final class CreateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User || ! $user->isPlatformUser()) {
            return false;
        }

        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            return $user->can('manage platform settings');
        } finally {
            setPermissionsTeamId($previousStoreId);
        }
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => Str::upper(trim((string) $this->input('code'))),
            'symbol' => trim((string) $this->input('symbol')),
            'symbol_position' => Str::lower(trim((string) $this->input('symbol_position', 'before'))),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'alpha:ascii',
                'size:3',
                Rule::notIn(['USD']),
                Rule::unique('currencies', 'code'),
            ],
            'symbol' => ['required', 'string', 'max:16'],
            'symbol_position' => ['required', Rule::in(['before', 'after'])],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'usd_exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99999999'],
            'is_active' => ['sometimes', 'boolean'],
            'is_base' => ['prohibited'],
        ];
    }
}
