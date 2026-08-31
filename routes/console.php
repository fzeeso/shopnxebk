<?php

use App\Support\Translations\TranslationRequestDispatcher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->withoutOverlapping();

if ((bool) config('idempotency.enabled', false)) {
    Schedule::command('idempotency:prune')
        ->hourly()
        ->withoutOverlapping();
}

Schedule::call(fn (): int => app(TranslationRequestDispatcher::class)->dispatchPending())
    ->name('translations:dispatch-pending')
    ->everyMinute()
    ->withoutOverlapping();
