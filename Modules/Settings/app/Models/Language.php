<?php

declare(strict_types=1);

namespace Modules\Settings\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Settings\Enums\LanguageDirection;

#[Fillable(['name', 'native_name', 'locale', 'direction', 'is_active'])]
final class Language extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return [
            'direction' => LanguageDirection::class,
            'is_active' => 'boolean',
        ];
    }
}
