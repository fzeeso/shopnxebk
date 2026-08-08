<?php

declare(strict_types=1);

namespace App\Support\Translations;

use Illuminate\Database\Schema\Blueprint;

final class TranslationSchema
{
    public static function addLock(Blueprint $table): void
    {
        $table->boolean('lock_it')->default(false);
    }
}
