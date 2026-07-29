<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Enums\FeatureValueType;

#[Fillable(['key', 'name', 'description', 'value_type', 'unit', 'is_addon_eligible', 'is_active'])]
final class Feature extends Model
{
    use HasPublicId;

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function valueTypeValue(): string
    {
        return $this->value_type instanceof FeatureValueType
            ? $this->value_type->value
            : (string) $this->value_type;
    }

    protected function casts(): array
    {
        return [
            'value_type' => FeatureValueType::class,
            'is_addon_eligible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
