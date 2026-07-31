<?php

declare(strict_types=1);

namespace Modules\Stores\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Authentication\Models\User;
use Modules\Stores\Enums\BusinessType;
use Modules\Stores\Enums\StoreStatus;
use Modules\Stores\Models\Store;

final class UpdateMerchantRequest extends FormRequest
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
            return $user->can('manage stores');
        } finally {
            setPermissionsTeamId($previousStoreId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    protected function prepareForValidation(): void
    {
        $owner = (array) $this->input('owner', []);
        $store = (array) $this->input('store', []);
        $owner['name'] = trim((string) ($owner['name'] ?? ''));
        $owner['email'] = Str::lower(trim((string) ($owner['email'] ?? '')));
        $store['name'] = trim((string) ($store['name'] ?? ''));
        $store['slug'] = Str::lower(trim((string) ($store['slug'] ?? '')));

        foreach (['email', 'primary_domain'] as $field) {
            if (array_key_exists($field, $store) && $store[$field] !== null) {
                $store[$field] = Str::lower(trim((string) $store[$field]));
            }
        }

        foreach (['currency_code', 'country_code'] as $field) {
            if (array_key_exists($field, $store) && $store[$field] !== null) {
                $store[$field] = Str::upper(trim((string) $store[$field]));
            }
        }

        if (array_key_exists('language_code', $store) && $store['language_code'] !== null) {
            $locale = str_replace('-', '_', trim((string) $store['language_code']));
            $parts = explode('_', $locale, 2);
            $store['language_code'] = Str::lower($parts[0]);
            if (isset($parts[1]) && $parts[1] !== '') {
                $store['language_code'] .= '_'.Str::upper($parts[1]);
            }
        }

        $this->merge(['owner' => $owner, 'store' => $store]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $store = Store::query()->where('public_id', (string) $this->route('merchant'))->first();
        $ownerId = $store === null ? null : DB::table('model_has_roles as assignments')
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->where('assignments.store_id', $store->getKey())
            ->where('assignments.model_type', User::class)
            ->where('roles.name', 'Owner')
            ->value('assignments.model_id');

        return [
            'owner' => ['required', 'array:name,email,password,password_confirmation'],
            'owner.name' => ['required', 'string', 'max:120'],
            'owner.email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($ownerId)],
            'owner.password' => ['sometimes', 'nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'store' => ['required', 'array:name,slug,legal_name,description,email,phone,primary_domain,industry,business_type,currency_code,language_code,timezone,country_code,status'],
            'store.name' => ['required', 'string', 'max:120'],
            'store.slug' => ['required', 'alpha_dash:ascii', 'min:3', 'max:80', Rule::unique('stores', 'slug')->ignore($store?->getKey())],
            'store.legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'store.description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'store.email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'store.phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'store.primary_domain' => ['sometimes', 'nullable', 'string', 'max:253', Rule::unique('stores', 'primary_domain')->ignore($store?->getKey())],
            'store.industry' => ['sometimes', 'nullable', 'string', 'max:120'],
            'store.business_type' => ['sometimes', 'nullable', Rule::enum(BusinessType::class)],
            'store.currency_code' => ['sometimes', 'string', 'alpha:ascii', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
            'store.language_code' => ['sometimes', 'string', 'max:10', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/', Rule::exists('languages', 'locale')->where('is_active', true)],
            'store.timezone' => ['sometimes', 'string', 'timezone:all'],
            'store.country_code' => ['sometimes', 'nullable', 'string', 'alpha:ascii', 'size:2'],
            'store.status' => ['required', Rule::enum(StoreStatus::class)],
        ];
    }
}
