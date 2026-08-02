<?php

declare(strict_types=1);

namespace Modules\Stores\Contracts;

use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;

interface StoreProvisioner
{
    /**
     * @param  array{
     *     theme_template_key?: string,
     *     primary_domain?: string|null,
     *     contact_email?: string|null,
     *     contact_phone?: string|null,
     *     store_country_code?: string|null,
     *     store_state?: string|null,
     *     store_city?: string|null,
     *     store_zip?: string|null,
     *     store_address_1?: string|null,
     *     store_address_2?: string|null,
     *     preferences?: array<string, mixed>
     * }  $options
     */
    public function provision(User $owner, string $name, string $slug, array $options = []): Store;
}
