<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_id',
    'contact_email',
    'contact_phone',
    'store_country_code',
    'store_state',
    'store_city',
    'store_zip',
    'store_address_1',
    'store_address_2',
    'weight_unit',
    'storefront_enabled',
    'password_enabled',
    'password_hash',
    'order_number_prefix',
    'logo_media_id',
    'favicon_media_id',
    'social_links',
    'extra_settings',
    'auto_store_translation_flag',
    'is_searchable_on_platform_flag',
])]
final class StoreSetting extends Model
{
    public $incrementing = false;

    protected $table = 'store_settings';

    protected $primaryKey = 'store_id';

    protected $keyType = 'int';

    protected $hidden = ['password_hash'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function faviconMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'favicon_media_id');
    }

    protected function casts(): array
    {
        return [
            'storefront_enabled' => 'boolean',
            'password_enabled' => 'boolean',
            'password_hash' => 'hashed',
            'social_links' => 'array',
            'extra_settings' => 'array',
            'auto_store_translation_flag' => 'boolean',
            'is_searchable_on_platform_flag' => 'boolean',
        ];
    }
}
