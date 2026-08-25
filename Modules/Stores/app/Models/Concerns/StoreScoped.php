<?php

declare(strict_types=1);

namespace Modules\Stores\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        static::saving(function (Model $model): void {
            $context = app(StoreContext::class);
            if ($model->getAttribute('store_id') === null) {
                $model->setAttribute('store_id', $context->require()->getKey());
            }

            self::ensureCurrentStoreOwns($model, $context);
        });

        static::deleting(function (Model $model): void {
            self::ensureCurrentStoreOwns($model, app(StoreContext::class));
        });
    }

    private static function ensureCurrentStoreOwns(Model $model, StoreContext $context): void
    {
        $storeId = $context->id();
        if ($storeId === null || (int) $model->getAttribute('store_id') === $storeId) {
            return;
        }

        throw (new ModelNotFoundException)->setModel($model::class, [$model->getKey()]);
    }
}
