<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Authentication\Models\User;

final class UpdateCurrencyRequest extends FormRequest
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
        $values = [];

        if ($this->exists('name')) {
            $values['name'] = trim((string) $this->input('name'));
        }
        if ($this->exists('symbol')) {
            $values['symbol'] = trim((string) $this->input('symbol'));
        }
        if ($this->exists('symbol_position')) {
            $values['symbol_position'] = Str::lower(trim((string) $this->input('symbol_position')));
        }

        $this->merge($values);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'symbol' => ['sometimes', 'required', 'string', 'max:16'],
            'symbol_position' => ['sometimes', 'required', Rule::in(['before', 'after'])],
            'decimal_places' => ['sometimes', 'required', 'integer', 'min:0', 'max:4'],
            'usd_exchange_rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'gt:0',
                'max:999999999999.99999999',
            ],
            'is_active' => ['sometimes', 'boolean'],
            'code' => ['prohibited'],
            'is_base' => ['prohibited'],
        ];
    }
}
