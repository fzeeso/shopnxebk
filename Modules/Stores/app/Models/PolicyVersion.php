<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Authentication\Models\User;
use Modules\Settings\Models\Language;

#[Fillable(['store_policy_id', 'language_id', 'version', 'content', 'created_by'])]
final class PolicyVersion extends Model
{
    use HasPublicId;

    public const UPDATED_AT = null;

    public function policy(): BelongsTo
    {
        return $this->belongsTo(StorePolicy::class, 'store_policy_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
