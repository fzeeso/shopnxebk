<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;

final class UpdatePlatformUserRequest extends FormRequest
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
            return $user->can('manage platform users');
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $managedUserId = User::query()
            ->where('public_id', (string) $this->route('user'))
            ->where('scope', AccessScope::Platform->value)
            ->value('id');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($managedUserId)],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('roles', 'name')->where(fn ($query) => $query
                    ->where('guard_name', 'web')
                    ->where('scope', AccessScope::Platform->value)
                    ->whereNull('store_id')),
            ],
        ];
    }
}
