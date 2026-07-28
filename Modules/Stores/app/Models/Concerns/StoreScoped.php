<?php

declare(strict_types=1);

namespace Modules\Stores\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Stores\Contracts\StoreContext;

trait StoreScoped
{
    protected static function bootStoreScoped(): void
    {
        static::addGlobalScope('store', function (Builder $builder): void {
            $storeId = app(StoreContext::class)->id();
            if ($storeId !== null) {
                $builder->where($builder->qualifyColumn('store_id'), $storeId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('store_id') === null) {
                $model->setAttribute('store_id', app(StoreContext::class)->require()->getKey());
            }
        });
    }
}
