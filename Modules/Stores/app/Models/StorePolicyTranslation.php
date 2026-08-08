<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Settings\Models\Language;

#[Fillable(['store_policy_id', 'language_id', 'title', 'content', 'seo_title', 'seo_description', 'lock_it'])]
final class StorePolicyTranslation extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return ['lock_it' => 'boolean'];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(StorePolicy::class, 'store_policy_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
