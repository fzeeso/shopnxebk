<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Catalog\Services\CustomObjectTranslationResolver;
use Modules\Stores\Contracts\StoreContext;

final class CustomObjectResourceSupport
{
    /** @param Collection<int, Model> $translations */
    public static function resolved(Collection $translations, Request $request): ?Model
    {
        $resolver = app(CustomObjectTranslationResolver::class);

        return $resolver->resolve(
            $translations,
            app(StoreContext::class)->require(),
            $resolver->requestedLocale($request),
        );
    }
}
